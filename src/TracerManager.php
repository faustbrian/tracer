<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer;

use Cline\Tracer\Conductors\RevisionConductor;
use Cline\Tracer\Conductors\StagingConductor;
use Cline\Tracer\Configuration\ModelConfigurationBuilder;
use Cline\Tracer\Configuration\ModelConfigurationRegistry;
use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Contracts\CauserResolver;
use Cline\Tracer\Contracts\DiffStrategy;
use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Contracts\Traceable;
use Cline\Tracer\Database\Models\Revision;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Events\StagedChangeApplied;
use Cline\Tracer\Exceptions\InvalidStrategyClassException;
use Cline\Tracer\Exceptions\StagedChangeAlreadyTerminalException;
use Cline\Tracer\Exceptions\StagedChangeNotApprovedException;
use Cline\Tracer\Exceptions\StagedChangeNotMutableException;
use Cline\Tracer\Exceptions\StagedChangeTargetNotFoundException;
use Cline\Tracer\Exceptions\UnknownApprovalStrategyException;
use Cline\Tracer\Exceptions\UnknownDiffStrategyException;
use Cline\Tracer\Resolvers\AuthCauserResolver;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;

use const true;

use function array_keys;
use function array_merge;
use function event;
use function is_a;

/**
 * Central manager for Tracer revision tracking and staging.
 *
 * Provides a unified interface for working with model revisions, staged changes,
 * and approval workflows. Supports configurable diff and approval strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class TracerManager
{
    /**
     * Registered diff strategies by identifier.
     *
     * @var array<string, class-string<DiffStrategy>>
     */
    private array $diffStrategies = [];

    /**
     * Registered approval strategies by identifier.
     *
     * @var array<string, class-string<ApprovalStrategy>>
     */
    private array $approvalStrategies = [];

    /**
     * Create a new TracerManager instance.
     *
     * @param Container                  $container             Service container for resolving strategy instances
     *                                                          and dependencies. Used to instantiate diff and approval
     *                                                          strategies dynamically based on configuration.
     * @param ModelConfigurationRegistry $configurationRegistry Registry that maintains per-model configuration
     *                                                          for revision tracking, staging, and approval workflows.
     *                                                          Stores diff and approval strategy mappings for each model.
     */
    public function __construct(
        private readonly Container $container,
        private readonly ModelConfigurationRegistry $configurationRegistry,
    ) {
        $this->registerConfiguredStrategies();
    }

    /**
     * Get or create a configuration builder for a model.
     *
     * @param class-string $modelClass
     */
    public function configure(string $modelClass): ModelConfigurationBuilder
    {
        return $this->configurationRegistry->configure($modelClass);
    }

    /**
     * Get the model configuration registry.
     */
    public function getConfigurationRegistry(): ModelConfigurationRegistry
    {
        return $this->configurationRegistry;
    }

    /**
     * Get a revision conductor for a traceable model.
     *
     * @param Model&Traceable $model
     */
    public function revisions(Model $model): RevisionConductor
    {
        return new RevisionConductor($this, $model);
    }

    /**
     * Get a staging conductor for a stageable model.
     *
     * @param Model&Stageable $model
     */
    public function staging(Model $model): StagingConductor
    {
        return new StagingConductor($this, $model);
    }

    /**
     * Get all revisions for a model.
     *
     * @param  Model&Traceable           $model
     * @return Collection<int, Revision>
     */
    public function getRevisions(Model $model): Collection
    {
        return $this->revisions($model)->all();
    }

    /**
     * Get all staged changes for a model.
     *
     * @param  Model&Stageable               $model
     * @return Collection<int, StagedChange>
     */
    public function getStagedChanges(Model $model): Collection
    {
        return $this->staging($model)->all();
    }

    /**
     * Stage changes for a model.
     *
     * Creates a new staged change record containing proposed modifications to the model.
     * The changes are not immediately applied; they must go through an approval workflow
     * before being applied to the target model.
     *
     * @param Model&Stageable      $model      The stageable model to create changes for
     * @param array<string, mixed> $attributes The proposed attribute values to stage
     * @param null|string          $reason     Optional reason explaining why the changes are needed
     *
     * @return StagedChange The newly created staged change record
     */
    public function stage(Model $model, array $attributes, ?string $reason = null): StagedChange
    {
        return $this->staging($model)->stage($attributes, $reason);
    }

    /**
     * Approve a staged change.
     *
     * Delegates to the configured approval strategy for the staged change. The strategy
     * determines the approval workflow (e.g., single approver, multi-level approval).
     *
     * @param StagedChange $stagedChange The staged change to approve
     * @param null|Model   $approver     The model representing who is approving (e.g., User)
     * @param null|string  $comment      Optional comment explaining the approval decision
     *
     * @return bool True if approval was successful, false otherwise
     */
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        /** @var ApprovalStrategy $strategy */
        $strategy = $this->resolveApprovalStrategy($stagedChange->approval_strategy);

        return $strategy->approve($stagedChange, $approver, $comment);
    }

    /**
     * Reject a staged change.
     *
     * Delegates to the configured approval strategy for the staged change. Rejection
     * marks the change as rejected and prevents it from being applied.
     *
     * @param StagedChange $stagedChange The staged change to reject
     * @param null|Model   $rejector     The model representing who is rejecting (e.g., User)
     * @param null|string  $reason       Optional reason explaining why the change was rejected
     *
     * @return bool True if rejection was successful, false otherwise
     */
    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool
    {
        /** @var ApprovalStrategy $strategy */
        $strategy = $this->resolveApprovalStrategy($stagedChange->approval_strategy);

        return $strategy->reject($stagedChange, $rejector, $reason);
    }

    /**
     * Apply a staged change to its target model.
     *
     * Applies the proposed values from the staged change to the target model and
     * saves it to the database. The staged change must be in an approved status
     * before it can be applied. Dispatches a StagedChangeApplied event on success.
     *
     * @param StagedChange $stagedChange The approved staged change to apply
     * @param null|Model   $appliedBy    The model representing who applied the change (e.g., User)
     *
     * @throws StagedChangeNotApprovedException    If the staged change is not in an approved status
     * @throws StagedChangeTargetNotFoundException If the target model no longer exists
     * @return bool                                True if the change was successfully applied
     */
    public function apply(StagedChange $stagedChange, ?Model $appliedBy = null): bool
    {
        if (!$stagedChange->status->canBeApplied()) {
            throw StagedChangeNotApprovedException::forStagedChange($stagedChange);
        }

        $stageable = $stagedChange->stageable;

        if ($stageable === null) {
            throw StagedChangeTargetNotFoundException::forStagedChange($stagedChange);
        }

        $diffStrategy = $this->resolveDiffStrategy($stagedChange->diff_strategy);

        $newValues = $diffStrategy->apply(
            $stageable->getAttributes(),
            $stagedChange->proposed_values,
            reverse: false,
        );

        $stageable->fill($newValues);
        $stageable->save();

        $stagedChange->status = StagedChangeStatus::Applied;
        $stagedChange->applied_at = Date::now();

        if ($appliedBy instanceof Model) {
            $stagedChange->metadata = array_merge($stagedChange->metadata ?? [], [
                'applied_by_type' => $appliedBy->getMorphClass(),
                'applied_by_id' => $appliedBy->getKey(),
            ]);
        }

        $stagedChange->save();

        if ($this->eventsEnabled()) {
            event(
                new StagedChangeApplied($stagedChange, $stageable, $appliedBy),
            );
        }

        return true;
    }

    /**
     * Cancel a staged change.
     *
     * @throws StagedChangeAlreadyTerminalException If already terminal
     */
    public function cancel(StagedChange $stagedChange): void
    {
        if ($stagedChange->status->isTerminal()) {
            throw StagedChangeAlreadyTerminalException::forStagedChange($stagedChange);
        }

        $stagedChange->status = StagedChangeStatus::Cancelled;
        $stagedChange->save();
    }

    /**
     * Update proposed values on a staged change.
     *
     * @param  array<string, mixed>            $values
     * @throws StagedChangeNotMutableException If not mutable
     */
    public function updateProposedValues(StagedChange $stagedChange, array $values): void
    {
        if (!$stagedChange->status->isMutable()) {
            throw StagedChangeNotMutableException::forStagedChange($stagedChange);
        }

        $stagedChange->proposed_values = array_merge($stagedChange->proposed_values, $values);
        $stagedChange->save();
    }

    /**
     * Revert a model to a specific revision.
     *
     * @param Model&Traceable $model
     */
    public function revertTo(Model $model, Revision|int $revision): bool
    {
        return $this->revisions($model)->revertTo($revision);
    }

    /**
     * Register a diff strategy.
     *
     * Registers a custom diff strategy that can be used for computing differences
     * between model states. The strategy is identified by a unique string identifier
     * and can be assigned to specific models via configuration.
     *
     * @param string                     $identifier    Unique identifier for the strategy (e.g., 'snapshot', 'delta')
     * @param class-string<DiffStrategy> $strategyClass Fully qualified class name implementing DiffStrategy
     *
     * @throws InvalidStrategyClassException If the class does not implement DiffStrategy
     */
    public function registerDiffStrategy(string $identifier, string $strategyClass): void
    {
        if (!is_a($strategyClass, DiffStrategy::class, true)) {
            throw InvalidStrategyClassException::forClass($strategyClass, DiffStrategy::class);
        }

        $this->diffStrategies[$identifier] = $strategyClass;
    }

    /**
     * Register an approval strategy.
     *
     * Registers a custom approval strategy that defines how staged changes are
     * approved or rejected. The strategy is identified by a unique string identifier
     * and can be assigned to specific models via configuration.
     *
     * @param string                         $identifier    Unique identifier for the strategy (e.g., 'simple', 'multi-level')
     * @param class-string<ApprovalStrategy> $strategyClass Fully qualified class name implementing ApprovalStrategy
     *
     * @throws InvalidStrategyClassException If the class does not implement ApprovalStrategy
     */
    public function registerApprovalStrategy(string $identifier, string $strategyClass): void
    {
        if (!is_a($strategyClass, ApprovalStrategy::class, true)) {
            throw InvalidStrategyClassException::forClass($strategyClass, ApprovalStrategy::class);
        }

        $this->approvalStrategies[$identifier] = $strategyClass;
    }

    /**
     * Resolve a diff strategy by identifier.
     *
     * Retrieves a registered diff strategy instance by its identifier. The strategy
     * is instantiated via the service container to allow dependency injection.
     *
     * @param string $identifier The unique identifier of the diff strategy
     *
     * @throws UnknownDiffStrategyException If no strategy is registered with the given identifier
     * @return DiffStrategy                 The resolved diff strategy instance
     */
    public function resolveDiffStrategy(string $identifier): DiffStrategy
    {
        if (!isset($this->diffStrategies[$identifier])) {
            throw UnknownDiffStrategyException::forIdentifier($identifier);
        }

        /** @var DiffStrategy */
        return $this->container->make($this->diffStrategies[$identifier]);
    }

    /**
     * Resolve the diff strategy for revisions of a specific model class.
     *
     * @param class-string $modelClass
     */
    public function resolveRevisionDiffStrategyForModel(string $modelClass): DiffStrategy
    {
        $strategyClass = $this->configurationRegistry->getRevisionDiffStrategy($modelClass);

        if ($strategyClass !== null) {
            /** @var DiffStrategy */
            return $this->container->make($strategyClass);
        }

        /** @var class-string<DiffStrategy> $defaultStrategy */
        $defaultStrategy = Config::get('tracer.default_diff_strategy');

        /** @var DiffStrategy */
        return $this->container->make($defaultStrategy);
    }

    /**
     * Resolve the diff strategy for staged changes of a specific model class.
     *
     * @param class-string $modelClass
     */
    public function resolveStagedDiffStrategyForModel(string $modelClass): DiffStrategy
    {
        $strategyClass = $this->configurationRegistry->getStagedDiffStrategy($modelClass);

        if ($strategyClass !== null) {
            /** @var DiffStrategy */
            return $this->container->make($strategyClass);
        }

        /** @var class-string<DiffStrategy> $defaultStrategy */
        $defaultStrategy = Config::get('tracer.default_diff_strategy');

        /** @var DiffStrategy */
        return $this->container->make($defaultStrategy);
    }

    /**
     * Resolve the approval strategy for a specific model class.
     *
     * @param class-string $modelClass
     */
    public function resolveApprovalStrategyForModel(string $modelClass): ApprovalStrategy
    {
        $strategyClass = $this->configurationRegistry->getApprovalStrategy($modelClass);

        if ($strategyClass !== null) {
            /** @var ApprovalStrategy */
            return $this->container->make($strategyClass);
        }

        /** @var class-string<ApprovalStrategy> $defaultStrategy */
        $defaultStrategy = Config::get('tracer.default_approval_strategy');

        /** @var ApprovalStrategy */
        return $this->container->make($defaultStrategy);
    }

    /**
     * Resolve an approval strategy by identifier.
     *
     * Retrieves a registered approval strategy instance by its identifier. The strategy
     * is instantiated via the service container to allow dependency injection.
     *
     * @param string $identifier The unique identifier of the approval strategy
     *
     * @throws UnknownApprovalStrategyException If no strategy is registered with the given identifier
     * @return ApprovalStrategy                 The resolved approval strategy instance
     */
    public function resolveApprovalStrategy(string $identifier): ApprovalStrategy
    {
        if (!isset($this->approvalStrategies[$identifier])) {
            throw UnknownApprovalStrategyException::forIdentifier($identifier);
        }

        /** @var ApprovalStrategy */
        return $this->container->make($this->approvalStrategies[$identifier]);
    }

    /**
     * Get all registered diff strategy identifiers.
     *
     * @return array<string>
     */
    public function getDiffStrategies(): array
    {
        return array_keys($this->diffStrategies);
    }

    /**
     * Get all registered approval strategy identifiers.
     *
     * @return array<string>
     */
    public function getApprovalStrategies(): array
    {
        return array_keys($this->approvalStrategies);
    }

    /**
     * Get pending staged changes across all models.
     *
     * @return Collection<int, StagedChange>
     */
    public function allPendingStagedChanges(): Collection
    {
        return StagedChange::query()
            ->where('status', StagedChangeStatus::Pending)->latest()
            ->get();
    }

    /**
     * Get approved staged changes across all models.
     *
     * @return Collection<int, StagedChange>
     */
    public function allApprovedStagedChanges(): Collection
    {
        return StagedChange::query()
            ->where('status', StagedChangeStatus::Approved)->latest()
            ->get();
    }

    /**
     * Resolve the causer for the current context.
     *
     * Uses the configured causer resolver to determine who caused a change.
     */
    public function resolveCauser(): ?Model
    {
        return $this->getCauserResolver()->resolve();
    }

    /**
     * Get the configured causer resolver.
     */
    public function getCauserResolver(): CauserResolver
    {
        /** @var class-string<CauserResolver> $resolverClass */
        $resolverClass = Config::get('tracer.causer_resolver', AuthCauserResolver::class);

        /** @var CauserResolver */
        return $this->container->make($resolverClass);
    }

    /**
     * Check if events are enabled.
     *
     * Determines whether Tracer should dispatch events (e.g., StagedChangeApplied).
     * Controlled via the 'tracer.events.enabled' configuration setting.
     *
     * @return bool True if events are enabled, false otherwise
     */
    public function eventsEnabled(): bool
    {
        /** @var bool */
        return Config::get('tracer.events.enabled', true);
    }

    /**
     * Register strategies from configuration.
     */
    private function registerConfiguredStrategies(): void
    {
        /** @var array<string, class-string<DiffStrategy>> $diffStrategies */
        $diffStrategies = Config::get('tracer.diff_strategies', []);

        foreach ($diffStrategies as $identifier => $strategyClass) {
            $this->diffStrategies[$identifier] = $strategyClass;
        }

        /** @var array<string, class-string<ApprovalStrategy>> $approvalStrategies */
        $approvalStrategies = Config::get('tracer.approval_strategies', []);

        foreach ($approvalStrategies as $identifier => $strategyClass) {
            $this->approvalStrategies[$identifier] = $strategyClass;
        }
    }
}
