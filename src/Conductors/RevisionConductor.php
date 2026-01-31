<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Conductors;

use Cline\Tracer\Contracts\DiffStrategy;
use Cline\Tracer\Contracts\Traceable;
use Cline\Tracer\Database\Models\Revision;
use Cline\Tracer\Enums\RevisionAction;
use Cline\Tracer\Events\RevisionCreated;
use Cline\Tracer\Exceptions\RevisionNotFoundForModelException;
use Cline\Tracer\TracerManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function array_keys;
use function array_unique;
use function event;
use function is_int;
use function method_exists;

/**
 * Fluent conductor for revision operations.
 *
 * Provides a chainable interface for querying and working with model revisions.
 * Contains all business logic for revision tracking.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RevisionConductor
{
    /**
     * Static registry of models with tracking disabled.
     *
     * Keyed by model class and key, storing disabled state.
     *
     * @var array<string, bool>
     */
    private static array $trackingDisabled = [];

    /**
     * @param TracerManager   $manager The tracer manager instance
     * @param Model&Traceable $model   The model to work with revisions for
     */
    public function __construct(
        private readonly TracerManager $manager,
        private readonly Model $model,
    ) {}

    /**
     * Get all revisions for the model.
     *
     * @return Collection<int, Revision>
     */
    public function all(): Collection
    {
        return $this->model->revisions()->get();
    }

    /**
     * Get the latest revision.
     *
     * @return null|Revision The most recent revision, or null if no revisions exist
     */
    public function latest(): ?Revision
    {
        return $this->model->latestRevision();
    }

    /**
     * Get a specific revision by version number.
     *
     * @param  int           $version The version number to retrieve (starting from 1)
     * @return null|Revision The matching revision, or null if not found
     */
    public function version(int $version): ?Revision
    {
        return $this->model->getRevision($version);
    }

    /**
     * Get revisions filtered by action type.
     *
     * @param  RevisionAction            $action The action type to filter by (Created, Updated, Deleted, etc.)
     * @return Collection<int, Revision>
     */
    public function byAction(RevisionAction $action): Collection
    {
        return $this->model->revisions()->where('action', $action)->get();
    }

    /**
     * Get revisions created by a specific user/entity.
     *
     * @param  Model                     $causer The model representing the entity that caused the revisions
     * @return Collection<int, Revision>
     */
    public function byCauser(Model $causer): Collection
    {
        return $this->model->revisions()
            ->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey())
            ->get();
    }

    /**
     * Get revisions that modified a specific attribute.
     *
     * @param  string                    $attribute The attribute name to search for changes
     * @return Collection<int, Revision>
     */
    public function forAttribute(string $attribute): Collection
    {
        return $this->model->revisions()
            ->get()
            ->filter(fn (Revision $revision): bool => $revision->hasChangedAttribute($attribute));
    }

    /**
     * Get the revision count.
     *
     * @return int Total number of revisions for this model
     */
    public function count(): int
    {
        return $this->model->revisions()->count();
    }

    /**
     * Revert to a specific revision.
     *
     * Reconstructs the model state at the target revision and applies it to the
     * current model. Creates a new "Reverted" revision to track this operation.
     * Temporarily disables tracking to avoid double-recording the revert.
     *
     * @param  int|Revision|string               $revision The revision to revert to (Revision instance, version number, or revision ID)
     * @throws RevisionNotFoundForModelException If the specified revision cannot be found
     * @return bool                              Whether the revert operation succeeded
     */
    public function revertTo(Revision|int|string $revision): bool
    {
        $targetRevision = $this->resolveRevision($revision);

        if (!$targetRevision instanceof Revision) {
            throw RevisionNotFoundForModelException::forModel($this->model, is_int($revision) ? $revision : (string) $revision);
        }

        // Reconstruct the state at that revision
        $targetState = $this->reconstructStateAtRevision($targetRevision);

        // Apply the state changes
        $this->model->fill($targetState);

        // Temporarily disable revision tracking to avoid double-tracking
        $wasEnabled = $this->isTrackingEnabled();
        $this->disableTracking();
        $saved = $this->model->save();

        if ($wasEnabled) {
            $this->enableTracking();
        }

        if ($saved) {
            // Create a revert revision
            $this->createRevision(
                RevisionAction::Reverted,
                $this->model->getOriginal(),
                $targetState,
                ['reverted_to_version' => $targetRevision->version],
            );
        }

        return $saved;
    }

    /**
     * Get the diff between two revisions.
     *
     * Compares the model state at two different revisions and returns the
     * differences between them. Returns an empty array if either revision
     * is not found.
     *
     * @param  int|Revision                                 $fromRevision Starting revision (Revision instance or version number)
     * @param  int|Revision                                 $toRevision   Ending revision (Revision instance or version number)
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function diff(Revision|int $fromRevision, Revision|int $toRevision): array
    {
        $from = $fromRevision instanceof Revision ? $fromRevision : $this->version($fromRevision);
        $to = $toRevision instanceof Revision ? $toRevision : $this->version($toRevision);

        if (!$from instanceof Revision || !$to instanceof Revision) {
            return [];
        }

        $fromState = $this->reconstructStateAt($from);
        $toState = $this->reconstructStateAt($to);

        /** @var array<string, array{old: mixed, new: mixed}> $diff */
        $diff = [];
        $allKeys = array_unique([...array_keys($fromState), ...array_keys($toState)]);

        foreach ($allKeys as $key) {
            /** @var string $key */
            $fromValue = $fromState[$key] ?? null;
            $toValue = $toState[$key] ?? null;

            if ($fromValue === $toValue) {
                continue;
            }

            $diff[$key] = [
                'old' => $fromValue,
                'new' => $toValue,
            ];
        }

        return $diff;
    }

    /**
     * Record a create action.
     *
     * Creates a revision entry tracking the initial creation of this model.
     * Records all tracked attributes as new values with empty old values.
     *
     * @return Revision The created revision record
     */
    public function recordCreated(): Revision
    {
        return $this->createRevision(
            RevisionAction::Created,
            [],
            $this->getTrackedAttributeValues(),
        );
    }

    /**
     * Record an update action.
     *
     * Creates a revision entry tracking changes to the model. Returns null if
     * no tracked attributes were actually changed.
     *
     * @return null|Revision The created revision record, or null if no changes detected
     */
    public function recordUpdated(): ?Revision
    {
        $changes = $this->getTrackedChanges();

        if ($changes === []) {
            return null;
        }

        return $this->createRevision(
            RevisionAction::Updated,
            $changes['old'],
            $changes['new'],
        );
    }

    /**
     * Record a delete action.
     *
     * Creates a revision entry tracking the deletion of this model. Distinguishes
     * between soft deletes and force deletes if the model uses SoftDeletes.
     *
     * @return Revision The created revision record
     */
    public function recordDeleted(): Revision
    {
        $action = method_exists($this->model, 'isForceDeleting') && $this->model->isForceDeleting()
            ? RevisionAction::ForceDeleted
            : RevisionAction::Deleted;

        return $this->createRevision($action, $this->getTrackedAttributeValues(), []);
    }

    /**
     * Record a restore action.
     *
     * Creates a revision entry tracking the restoration of a soft-deleted model.
     *
     * @return Revision The created revision record
     */
    public function recordRestored(): Revision
    {
        return $this->createRevision(
            RevisionAction::Restored,
            [],
            $this->getTrackedAttributeValues(),
        );
    }

    /**
     * Check if tracking is enabled.
     *
     * State is stored in a static registry to persist across conductor instances,
     * ensuring consistent behavior when multiple conductors operate on the same model.
     *
     * @return bool Whether tracking is currently enabled for this model instance
     */
    public function isTrackingEnabled(): bool
    {
        $key = $this->getTrackingKey();

        return !isset(self::$trackingDisabled[$key]) || !self::$trackingDisabled[$key];
    }

    /**
     * Disable tracking for this model.
     *
     * State is stored in a static registry to persist across conductor instances.
     * Useful when performing operations that should not create revisions.
     *
     * @return static For method chaining
     */
    public function disableTracking(): static
    {
        self::$trackingDisabled[$this->getTrackingKey()] = true;

        return $this;
    }

    /**
     * Enable tracking for this model.
     *
     * State is stored in a static registry to persist across conductor instances.
     * Re-enables tracking after it was previously disabled.
     *
     * @return static For method chaining
     */
    public function enableTracking(): static
    {
        unset(self::$trackingDisabled[$this->getTrackingKey()]);

        return $this;
    }

    /**
     * Execute a callback without tracking.
     *
     * Temporarily disables revision tracking for the duration of the callback,
     * then restores the previous tracking state. Useful for bulk operations or
     * internal updates that should not create revision history.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn $callback The callback to execute without tracking
     * @return TReturn             The callback's return value
     */
    public function withoutTracking(callable $callback): mixed
    {
        $wasEnabled = $this->isTrackingEnabled();
        $this->disableTracking();

        try {
            return $callback();
        } finally {
            if ($wasEnabled) {
                $this->enableTracking();
            }
        }
    }

    /**
     * Create a new revision record.
     *
     * Stores a new revision entry with the specified action, old/new values, and
     * metadata. Automatically increments the version number and resolves the causer.
     * Dispatches RevisionCreated event if events are enabled.
     *
     * @param  RevisionAction       $action   The action that triggered this revision
     * @param  array<string, mixed> $old      Old attribute values
     * @param  array<string, mixed> $new      New attribute values
     * @param  array<string, mixed> $metadata Additional metadata
     * @return Revision             The created revision
     */
    public function createRevision(
        RevisionAction $action,
        array $old,
        array $new,
        array $metadata = [],
    ): Revision {
        $nextVersion = $this->getNextRevisionVersion();
        $diffStrategy = $this->resolveDiffStrategy();

        /** @var Revision $revision */
        $revision = $this->model->revisions()->create([
            'version' => $nextVersion,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'diff_strategy' => $diffStrategy->identifier(),
            'causer_type' => $this->getRevisionCauserType(),
            'causer_id' => $this->getRevisionCauserId(),
            'metadata' => $metadata,
        ]);

        if ($this->manager->eventsEnabled()) {
            event(
                new RevisionCreated($revision, $this->model),
            );
        }

        return $revision;
    }

    /**
     * Get the tracked attribute values.
     *
     * Filters the model's current attributes to only those configured for tracking,
     * respecting both included and excluded attribute configurations.
     *
     * @return array<string, mixed> The filtered attributes that should be tracked
     */
    public function getTrackedAttributeValues(): array
    {
        $modelClass = $this->model::class;
        $registry = $this->manager->getConfigurationRegistry();

        return $registry->getTrackedAttributes($modelClass, $this->model->getAttributes());
    }

    /**
     * Get the changes that should be tracked.
     *
     * Compares the model's dirty attributes against the original values and
     * filters to only tracked attributes that have actually changed.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>} Old and new values for changed attributes
     */
    public function getTrackedChanges(): array
    {
        $modelClass = $this->model::class;
        $registry = $this->manager->getConfigurationRegistry();

        $dirty = $registry->getTrackedAttributes($modelClass, $this->model->getDirty());

        /** @var array<string, mixed> $old */
        $old = [];

        /** @var array<string, mixed> $new */
        $new = [];

        if ($dirty === []) {
            return ['old' => $old, 'new' => $new];
        }

        foreach ($dirty as $key => $newValue) {
            $originalValue = $this->model->getOriginal($key);

            // Only track if the value actually changed
            if ($originalValue === $newValue) {
                continue;
            }

            $old[$key] = $originalValue;
            $new[$key] = $newValue;
        }

        return ['old' => $old, 'new' => $new];
    }

    /**
     * Get the tracking registry key for the current model.
     *
     * Creates a unique key for this model instance in the static tracking registry,
     * combining the morph class and model key.
     *
     * @return string The unique tracking key
     */
    private function getTrackingKey(): string
    {
        /** @var int|string $key */
        $key = $this->model->getKey();

        return $this->model->getMorphClass().':'.$key;
    }

    /**
     * Reconstruct the model state at a specific revision.
     *
     * Uses each revision's diff strategy to properly apply changes,
     * ensuring compatibility with different diff formats.
     *
     * @return array<string, mixed>
     */
    private function reconstructStateAt(Revision $targetRevision): array
    {
        /** @var array<string, mixed> $state */
        $state = [];

        // Get all revisions up to and including the target
        $revisions = $this->model->revisions()
            ->where('version', '<=', $targetRevision->version)
            ->orderBy('version')
            ->get();

        foreach ($revisions as $revision) {
            /** @var string $strategyIdentifier */
            $strategyIdentifier = $revision->diff_strategy;
            $strategy = $this->manager->resolveDiffStrategy($strategyIdentifier);
            $diff = [
                'old' => $revision->old_values,
                'new' => $revision->new_values,
            ];
            $state = $strategy->apply($state, $diff, reverse: false);
        }

        return $state;
    }

    /**
     * Reconstruct the model state at a specific revision for revert.
     *
     * Uses each revision's diff strategy to properly apply changes in reverse,
     * ensuring compatibility with different diff formats.
     *
     * @return array<string, mixed>
     */
    private function reconstructStateAtRevision(Revision $targetRevision): array
    {
        // Start with current state
        $state = $this->getTrackedAttributeValues();

        // Get all revisions after the target, in reverse chronological order
        $revisionsToUndo = $this->model->revisions()
            ->where('version', '>', $targetRevision->version)
            ->orderByDesc('version')
            ->get();

        // Apply each revision in reverse using its strategy
        foreach ($revisionsToUndo as $revision) {
            /** @var string $strategyIdentifier */
            $strategyIdentifier = $revision->diff_strategy;
            $strategy = $this->manager->resolveDiffStrategy($strategyIdentifier);
            $diff = [
                'old' => $revision->old_values,
                'new' => $revision->new_values,
            ];
            $state = $strategy->apply($state, $diff, reverse: true);
        }

        // Apply the target revision's state using its strategy
        /** @var string $targetStrategyIdentifier */
        $targetStrategyIdentifier = $targetRevision->diff_strategy;
        $targetStrategy = $this->manager->resolveDiffStrategy($targetStrategyIdentifier);
        $targetDiff = [
            'old' => $targetRevision->old_values,
            'new' => $targetRevision->new_values,
        ];

        return $targetStrategy->apply($state, $targetDiff, reverse: false);
    }

    /**
     * Resolve a revision from various input types.
     *
     * Accepts a Revision instance, version number (int), or revision ID (string)
     * and returns the corresponding Revision instance.
     *
     * @param  int|Revision|string $revision The revision to resolve
     * @return null|Revision       The resolved revision, or null if not found
     */
    private function resolveRevision(Revision|int|string $revision): ?Revision
    {
        if ($revision instanceof Revision) {
            return $revision;
        }

        // Try as version number first (for integers)
        if (is_int($revision)) {
            return $this->model->getRevision($revision);
        }

        // Try as ID
        return $this->model->revisions()->find($revision);
    }

    /**
     * Get the next revision version number.
     *
     * Calculates the next sequential version number by finding the current maximum
     * version and incrementing it.
     *
     * @return int The next version number (starting from 1 for first revision)
     */
    private function getNextRevisionVersion(): int
    {
        /** @var null|int $latestVersion */
        $latestVersion = $this->model->revisions()->max('version');

        return ($latestVersion ?? 0) + 1;
    }

    /**
     * Resolve the diff strategy for this model.
     *
     * Gets the configured diff strategy for revision tracking, either from model-specific
     * configuration or the global default.
     *
     * @return DiffStrategy The diff strategy instance to use
     */
    private function resolveDiffStrategy(): DiffStrategy
    {
        return $this->manager->resolveRevisionDiffStrategyForModel($this->model::class);
    }

    /**
     * Get the morph type of the entity that caused this revision.
     *
     * @return null|string The morph class name, or null if no causer
     */
    private function getRevisionCauserType(): ?string
    {
        $causer = $this->getRevisionCauser();

        return $causer?->getMorphClass();
    }

    /**
     * Get the ID of the entity that caused this revision.
     *
     * @return null|int|string The primary key value, or null if no causer
     */
    private function getRevisionCauserId(): int|string|null
    {
        $causer = $this->getRevisionCauser();

        if (!$causer instanceof Model) {
            return null;
        }

        /** @var int|string */
        return $causer->getKey();
    }

    /**
     * Get the entity that caused this revision.
     *
     * Uses the configured causer resolver from TracerManager to determine who
     * is responsible for the current change (typically the authenticated user).
     *
     * @return null|Model The causer model instance, or null if anonymous
     */
    private function getRevisionCauser(): ?Model
    {
        return $this->manager->resolveCauser();
    }
}
