<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Conductors;

use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Contracts\DiffStrategy;
use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Events\StagedChangeCreated;
use Cline\Tracer\TracerManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function array_keys;
use function event;

/**
 * Fluent conductor for staging operations.
 *
 * Provides a chainable interface for staging changes and managing approval workflows.
 * Contains all business logic for staged changes.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StagingConductor
{
    /**
     * @param TracerManager   $manager The tracer manager instance
     * @param Model&Stageable $model   The model to work with staged changes for
     */
    public function __construct(
        private TracerManager $manager,
        private Model $model,
    ) {}

    /**
     * Stage new changes for the model.
     *
     * Creates a new staged change record with the proposed attribute changes.
     * Only stageable attributes (as configured) are included. The change enters
     * Pending status and awaits approval based on the configured approval strategy.
     *
     * @param  array<string, mixed> $attributes The attributes to stage
     * @param  null|string          $reason     Optional reason for the change
     * @return StagedChange         The created staged change record
     */
    public function stage(array $attributes, ?string $reason = null): StagedChange
    {
        $diffStrategy = $this->resolveDiffStrategy();
        $approvalStrategy = $this->resolveApprovalStrategy();

        // Filter attributes to only stageable ones
        $stageableAttributes = $this->filterStageableAttributes($attributes);
        $originalValues = $this->getOriginalValues(array_keys($stageableAttributes));

        /** @var StagedChange $stagedChange */
        $stagedChange = $this->model->stagedChanges()->create([
            'original_values' => $originalValues,
            'proposed_values' => $stageableAttributes,
            'diff_strategy' => $diffStrategy->identifier(),
            'approval_strategy' => $approvalStrategy->identifier(),
            'status' => StagedChangeStatus::Pending,
            'reason' => $reason,
            'author_type' => $this->getAuthorType(),
            'author_id' => $this->getAuthorId(),
        ]);

        if ($this->manager->eventsEnabled()) {
            event(
                new StagedChangeCreated($stagedChange, $this->model),
            );
        }

        return $stagedChange;
    }

    /**
     * Get all staged changes for the model.
     *
     * Returns staged changes in all statuses, ordered by creation date descending.
     *
     * @return Collection<int, StagedChange>
     */
    public function all(): Collection
    {
        return $this->model->stagedChanges()->get();
    }

    /**
     * Get pending staged changes.
     *
     * Returns only changes with Pending status that are awaiting approval.
     *
     * @return Collection<int, StagedChange>
     */
    public function pending(): Collection
    {
        return $this->model->pendingStagedChanges()->get();
    }

    /**
     * Get approved staged changes.
     *
     * Returns only changes with Approved status that are ready to be applied.
     *
     * @return Collection<int, StagedChange>
     */
    public function approved(): Collection
    {
        return $this->model->approvedStagedChanges()->get();
    }

    /**
     * Get staged changes filtered by status.
     *
     * @param  StagedChangeStatus            $status The status to filter by
     * @return Collection<int, StagedChange>
     */
    public function byStatus(StagedChangeStatus $status): Collection
    {
        return $this->model->stagedChanges()->where('status', $status)->get();
    }

    /**
     * Get staged changes created by a specific user/entity.
     *
     * @param  Model                         $author The model representing the entity that authored the staged changes
     * @return Collection<int, StagedChange>
     */
    public function byAuthor(Model $author): Collection
    {
        return $this->model->stagedChanges()
            ->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey())
            ->get();
    }

    /**
     * Check if there are pending staged changes.
     *
     * @return bool Whether any pending staged changes exist
     */
    public function hasPending(): bool
    {
        return $this->model->pendingStagedChanges()->exists();
    }

    /**
     * Check if there are approved changes ready to apply.
     *
     * @return bool Whether any approved staged changes exist
     */
    public function hasApproved(): bool
    {
        return $this->model->approvedStagedChanges()->exists();
    }

    /**
     * Apply all approved changes.
     *
     * Iterates through all approved staged changes and applies them to the model.
     * Each change is processed through the TracerManager's apply method.
     *
     * @param  null|Model $appliedBy Optional model representing who applied the changes
     * @return int        Number of changes applied
     */
    public function applyApproved(?Model $appliedBy = null): int
    {
        $applied = 0;

        /** @var StagedChange $stagedChange */
        foreach ($this->model->approvedStagedChanges()->get() as $stagedChange) {
            $this->manager->apply($stagedChange, $appliedBy);
            ++$applied;
        }

        return $applied;
    }

    /**
     * Cancel all pending changes.
     *
     * Iterates through all pending staged changes and cancels them via the
     * TracerManager's cancel method.
     *
     * @return int Number of changes cancelled
     */
    public function cancelPending(): int
    {
        $cancelled = 0;

        /** @var StagedChange $stagedChange */
        foreach ($this->model->pendingStagedChanges()->get() as $stagedChange) {
            $this->manager->cancel($stagedChange);
            ++$cancelled;
        }

        return $cancelled;
    }

    /**
     * Update proposed values on a staged change.
     *
     * Modifies the proposed changes on an existing staged change record.
     *
     * @param StagedChange         $stagedChange The staged change to update
     * @param array<string, mixed> $values       New proposed values
     */
    public function updateProposedValues(StagedChange $stagedChange, array $values): void
    {
        $this->manager->updateProposedValues($stagedChange, $values);
    }

    /**
     * Get the staged change count.
     *
     * @return int Total number of staged changes in all statuses
     */
    public function count(): int
    {
        return $this->model->stagedChanges()->count();
    }

    /**
     * Get the pending count.
     *
     * @return int Number of staged changes with Pending status
     */
    public function pendingCount(): int
    {
        return $this->model->pendingStagedChanges()->count();
    }

    /**
     * Approve a specific staged change.
     *
     * Delegates to the configured approval strategy to record the approval and
     * determine if the change is now fully approved.
     *
     * @param  StagedChange $stagedChange The staged change to approve
     * @param  null|Model   $approver     The user/entity approving (null for system)
     * @param  null|string  $comment      Optional approval comment
     * @return bool         Whether the change is now fully approved
     */
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        $strategy = $this->manager->resolveApprovalStrategy($stagedChange->approval_strategy);

        return $strategy->approve($stagedChange, $approver, $comment);
    }

    /**
     * Reject a specific staged change.
     *
     * Delegates to the configured approval strategy to record the rejection.
     *
     * @param  StagedChange $stagedChange The staged change to reject
     * @param  null|Model   $rejector     The user/entity rejecting (null for system)
     * @param  null|string  $reason       Optional rejection reason
     * @return bool         Whether the rejection was recorded successfully
     */
    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool
    {
        $strategy = $this->manager->resolveApprovalStrategy($stagedChange->approval_strategy);

        return $strategy->reject($stagedChange, $rejector, $reason);
    }

    /**
     * Get the approval status for a specific staged change.
     *
     * Delegates to the configured approval strategy to get detailed status information
     * such as required approvals, current approvers, and approval progress.
     *
     * @param  StagedChange         $stagedChange The staged change to check
     * @return array<string, mixed> Status information from the approval strategy
     */
    public function approvalStatus(StagedChange $stagedChange): array
    {
        $strategy = $this->manager->resolveApprovalStrategy($stagedChange->approval_strategy);

        return $strategy->status($stagedChange);
    }

    /**
     * Filter attributes to only those that can be staged.
     *
     * Applies model-specific stageable configuration to remove attributes
     * that should not be staged.
     *
     * @param  array<string, mixed> $attributes The proposed attributes
     * @return array<string, mixed> Filtered attributes that can be staged
     */
    private function filterStageableAttributes(array $attributes): array
    {
        $modelClass = $this->model::class;
        $registry = $this->manager->getConfigurationRegistry();

        return $registry->getStageableAttributes($modelClass, $attributes);
    }

    /**
     * Get the original values for specific attribute keys.
     *
     * Retrieves the current model attribute values that will be recorded as
     * the "before" state for the staged change.
     *
     * @param  array<string>        $keys Attribute names to retrieve
     * @return array<string, mixed> Current attribute values
     */
    private function getOriginalValues(array $keys): array
    {
        $original = [];

        foreach ($keys as $key) {
            $original[$key] = $this->model->getAttribute($key);
        }

        return $original;
    }

    /**
     * Resolve the diff strategy for staged changes.
     *
     * Gets the configured diff strategy for staging, either from model-specific
     * configuration or the global default.
     *
     * @return DiffStrategy The diff strategy instance to use
     */
    private function resolveDiffStrategy(): DiffStrategy
    {
        return $this->manager->resolveStagedDiffStrategyForModel($this->model::class);
    }

    /**
     * Resolve the approval strategy.
     *
     * Gets the configured approval strategy, either from model-specific
     * configuration or the global default.
     *
     * @return ApprovalStrategy The approval strategy instance to use
     */
    private function resolveApprovalStrategy(): ApprovalStrategy
    {
        return $this->manager->resolveApprovalStrategyForModel($this->model::class);
    }

    /**
     * Get the morph type of the entity authoring this staged change.
     *
     * @return null|string The morph class name, or null if no author
     */
    private function getAuthorType(): ?string
    {
        $author = $this->getAuthor();

        return $author?->getMorphClass();
    }

    /**
     * Get the ID of the entity authoring this staged change.
     *
     * @return null|int|string The primary key value, or null if no author
     */
    private function getAuthorId(): int|string|null
    {
        $author = $this->getAuthor();

        if (!$author instanceof Model) {
            return null;
        }

        /** @var int|string */
        return $author->getKey();
    }

    /**
     * Get the entity authoring this staged change.
     *
     * Uses the configured causer resolver from TracerManager to determine who
     * is creating the staged change (typically the authenticated user).
     *
     * @return null|Model The author model instance, or null if anonymous
     */
    private function getAuthor(): ?Model
    {
        return $this->manager->resolveCauser();
    }
}
