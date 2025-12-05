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
use Cline\Tracer\Database\Models\Revision;
use Cline\Tracer\Database\Models\StagedChange;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for the Tracer package.
 *
 * Provides static access to TracerManager functionality for tracking model revisions,
 * managing staged changes, and handling approval workflows. All method calls are
 * proxied to the underlying TracerManager singleton instance.
 *
 * @method static Collection<int, StagedChange> allApprovedStagedChanges()                                                            Retrieve all approved staged changes across all models
 * @method static Collection<int, StagedChange> allPendingStagedChanges()                                                             Retrieve all pending staged changes across all models
 * @method static bool                          apply(StagedChange $stagedChange, ?Model $appliedBy = null)                           Apply an approved staged change to its target model
 * @method static bool                          approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null) Approve a pending staged change
 * @method static void                          cancel(StagedChange $stagedChange)                                                    Cancel a non-terminal staged change
 * @method static ModelConfigurationBuilder     configure(string $modelClass)                                                         Get or create a configuration builder for a model class
 * @method static array<string>                 getApprovalStrategies()                                                               Get all registered approval strategy identifiers
 * @method static CauserResolver                getCauserResolver()                                                                   Get the configured causer resolver instance
 * @method static ModelConfigurationRegistry    getConfigurationRegistry()                                                            Get the model configuration registry
 * @method static array<string>                 getDiffStrategies()                                                                   Get all registered diff strategy identifiers
 * @method static Collection<int, Revision>     getRevisions(Model $model)                                                            Get all revisions for a traceable model
 * @method static Collection<int, StagedChange> getStagedChanges(Model $model)                                                        Get all staged changes for a stageable model
 * @method static void                          registerApprovalStrategy(string $identifier, string $strategyClass)                   Register a custom approval strategy
 * @method static void                          registerDiffStrategy(string $identifier, string $strategyClass)                       Register a custom diff strategy
 * @method static bool                          reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null)   Reject a pending staged change
 * @method static ApprovalStrategy              resolveApprovalStrategy(string $identifier)                                           Resolve an approval strategy by identifier
 * @method static ApprovalStrategy              resolveApprovalStrategyForModel(string $modelClass)                                   Resolve the approval strategy for a specific model class
 * @method static Model|null                    resolveCauser()                                                                       Resolve the causer for the current context
 * @method static DiffStrategy                  resolveDiffStrategy(string $identifier)                                               Resolve a diff strategy by identifier
 * @method static DiffStrategy                  resolveRevisionDiffStrategyForModel(string $modelClass)                               Resolve the revision diff strategy for a model class
 * @method static DiffStrategy                  resolveStagedDiffStrategyForModel(string $modelClass)                                 Resolve the staged diff strategy for a model class
 * @method static bool                          revertTo(Model $model, Revision|int $revision)                                        Revert a model to a specific revision
 * @method static RevisionConductor             revisions(Model $model)                                                               Get a revision conductor for a traceable model
 * @method static StagedChange                  stage(Model $model, array $attributes, ?string $reason = null)                        Stage changes for a model
 * @method static StagingConductor              staging(Model $model)                                                                 Get a staging conductor for a stageable model
 * @method static void                          updateProposedValues(StagedChange $stagedChange, array<string, mixed> $values)        Update proposed values on a mutable staged change
 *
 * @author Brian Faust <brian@cline.sh>
 * @see TracerManager
 */
final class Tracer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string The service container binding key for TracerManager
     */
    protected static function getFacadeAccessor(): string
    {
        return TracerManager::class;
    }
}
