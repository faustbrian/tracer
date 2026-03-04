<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Resolvers\AuthCauserResolver;
use Cline\Tracer\Strategies\Approval\QuorumApprovalStrategy;
use Cline\Tracer\Strategies\Approval\SimpleApprovalStrategy;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;
use Cline\Tracer\Strategies\Diff\SnapshotDiffStrategy;

return [

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | The type of primary key to use for Tracer's internal tables. Supported
    | values are: 'id' (auto-incrementing), 'uuid', or 'ulid'.
    |
    */

    'primary_key_type' => env('TRACER_PRIMARY_KEY_TYPE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Morph Type
    |--------------------------------------------------------------------------
    |
    | The column type used for morphable ID columns. Supported values are:
    | 'string', 'uuid', or 'ulid'.
    |
    */

    'morph_type' => env('TRACER_MORPH_TYPE', 'string'),

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure revision tracking and staging behavior per model. Each model
    | class can have its own configuration for tracked/untracked attributes,
    | diff strategies, and approval strategies.
    |
    | You can also configure models at runtime via Tracer::configure():
    |
    | Tracer::configure(Article::class)
    |     ->trackAttributes(['title', 'content'])
    |     ->untrackAttributes(['internal_notes'])
    |     ->revisionDiffStrategy(AttributeDiffStrategy::class)
    |     ->stageableAttributes(['title', 'content'])
    |     ->approvalStrategy(QuorumApprovalStrategy::class);
    |
    */

    'models' => [
        // Example configuration:
        // App\Models\Article::class => [
        //     'tracked_attributes' => ['title', 'content', 'status'],
        //     'untracked_attributes' => ['internal_notes'],
        //     'revision_diff_strategy' => AttributeDiffStrategy::class,
        //     'stageable_attributes' => ['title', 'content'],
        //     'unstageable_attributes' => ['admin_only'],
        //     'staged_diff_strategy' => SnapshotDiffStrategy::class,
        //     'approval_strategy' => QuorumApprovalStrategy::class,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Causer Resolver
    |--------------------------------------------------------------------------
    |
    | The resolver class that determines who caused a revision or authored a
    | staged change. The default resolver uses Laravel's authentication system.
    | You can create custom resolvers for queue jobs, console commands, API
    | tokens, or other contexts.
    |
    */

    'causer_resolver' => AuthCauserResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by Tracer. This allows you to avoid
    | conflicts with existing tables in your application. Each table serves
    | a specific purpose in the revision tracking and staging workflow.
    |
    */

    'table_names' => [

        /*
        |--------------------------------------------------------------------------
        | Revisions Table
        |--------------------------------------------------------------------------
        |
        | The table for storing model revision history. Each row represents a
        | snapshot of changes made to a trackable model, including the diff data,
        | causer information, and metadata about the change event.
        |
        */

        'revisions' => 'revisions',

        /*
        |--------------------------------------------------------------------------
        | Staged Changes Table
        |--------------------------------------------------------------------------
        |
        | The table for storing pending changes awaiting approval. Staged changes
        | capture proposed modifications to a model that must go through an
        | approval workflow before being applied to the actual model.
        |
        */

        'staged_changes' => 'staged_changes',

        /*
        |--------------------------------------------------------------------------
        | Staged Change Approvals Table
        |--------------------------------------------------------------------------
        |
        | The pivot table for tracking approval and rejection decisions on staged
        | changes. Each row represents a single approver's decision (approve or
        | reject) along with optional comments and timestamps.
        |
        */

        'staged_change_approvals' => 'staged_change_approvals',

    ],

    /*
    |--------------------------------------------------------------------------
    | Diff Strategies
    |--------------------------------------------------------------------------
    |
    | Register diff strategies that determine how model changes are stored.
    | Each strategy has a unique identifier used to resolve it at runtime.
    | Strategies control the format and granularity of stored change data,
    | with trade-offs between storage efficiency and query simplicity.
    |
    | You can register custom strategies by adding them here.
    |
    */

    'diff_strategies' => [

        /*
        |--------------------------------------------------------------------------
        | Snapshot Diff Strategy
        |--------------------------------------------------------------------------
        |
        | The snapshot strategy stores complete old and new values for the entire
        | model state. This approach is simple to understand and query, but uses
        | more storage space. Ideal when you need to easily reconstruct the full
        | model state at any point in time.
        |
        */

        'snapshot' => SnapshotDiffStrategy::class,

        /*
        |--------------------------------------------------------------------------
        | Attribute Diff Strategy
        |--------------------------------------------------------------------------
        |
        | The attribute strategy stores per-attribute changes, only recording the
        | specific fields that changed. This is more storage-efficient than
        | snapshots but requires more complex logic to reconstruct full states.
        | Best for models with many attributes where only a few change at a time.
        |
        */

        'attribute' => AttributeDiffStrategy::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Diff Strategy
    |--------------------------------------------------------------------------
    |
    | The default diff strategy used when tracking revisions or staging changes.
    | Models can override this by implementing their own strategy resolution.
    |
    */

    'default_diff_strategy' => SnapshotDiffStrategy::class,

    /*
    |--------------------------------------------------------------------------
    | Approval Strategies
    |--------------------------------------------------------------------------
    |
    | Register approval strategies that determine the workflow for staged changes.
    | Each strategy has a unique identifier used to resolve it at runtime.
    | Strategies define the rules for when a staged change can be approved,
    | rejected, or requires additional review.
    |
    | You can register custom strategies by adding them here.
    |
    */

    'approval_strategies' => [

        /*
        |--------------------------------------------------------------------------
        | Simple Approval Strategy
        |--------------------------------------------------------------------------
        |
        | The simple strategy requires only a single approval to apply a staged
        | change. This is suitable for small teams or low-risk changes where a
        | single reviewer's approval is sufficient. One rejection will reject
        | the entire staged change.
        |
        */

        'simple' => SimpleApprovalStrategy::class,

        /*
        |--------------------------------------------------------------------------
        | Quorum Approval Strategy
        |--------------------------------------------------------------------------
        |
        | The quorum strategy requires a configurable number of approvals before
        | a staged change can be applied. This is ideal for high-risk changes,
        | larger teams, or compliance requirements where multiple reviewers must
        | sign off. Configure thresholds in the 'quorum' section below.
        |
        */

        'quorum' => QuorumApprovalStrategy::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Approval Strategy
    |--------------------------------------------------------------------------
    |
    | The default approval strategy used when staging changes. Models can
    | override this by implementing getApprovalStrategy() method.
    |
    */

    'default_approval_strategy' => SimpleApprovalStrategy::class,

    /*
    |--------------------------------------------------------------------------
    | Quorum Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the quorum approval strategy. These settings define
    | the thresholds for how many approvals or rejections are needed to
    | finalize a staged change when using the quorum strategy.
    |
    */

    'quorum' => [

        /*
        |--------------------------------------------------------------------------
        | Approvals Required
        |--------------------------------------------------------------------------
        |
        | The minimum number of unique approvals required before a staged change
        | can be applied to the model. Setting this higher increases oversight
        | but may slow down the approval workflow. A value of 2 means at least
        | two different approvers must approve the change.
        |
        */

        'approvals_required' => 2,

        /*
        |--------------------------------------------------------------------------
        | Rejections Required
        |--------------------------------------------------------------------------
        |
        | The minimum number of rejections required to reject a staged change
        | and prevent it from being applied. A value of 1 means any single
        | rejection will block the change. Higher values allow for minority
        | dissent without blocking the overall workflow.
        |
        */

        'rejections_required' => 1,

    ],

    /*
    |--------------------------------------------------------------------------
    | Untracked Attributes
    |--------------------------------------------------------------------------
    |
    | Attributes that should never be tracked in revisions by default.
    | Models can add to this list via their $untrackedAttributes property.
    |
    */

    'untracked_attributes' => [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Unstageable Attributes
    |--------------------------------------------------------------------------
    |
    | Attributes that cannot be staged for approval by default.
    | Models can add to this list via their $unstageableAttributes property.
    |
    */

    'unstageable_attributes' => [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph Key Map
    |--------------------------------------------------------------------------
    |
    | Define custom morph key mappings for polymorphic relationships. These
    | mappings are merged with Laravel's existing morph map, allowing you to
    | use short aliases instead of fully qualified class names in the database.
    |
    | Example:
    | 'morphKeyMap' => [
    |     App\Models\User::class => 'user',
    |     App\Models\Post::class => 'post',
    | ],
    |
    */

    'morphKeyMap' => [],

    /*
    |--------------------------------------------------------------------------
    | Enforce Morph Key Map
    |--------------------------------------------------------------------------
    |
    | When set, this morph map will completely replace Laravel's default morph
    | map rather than merging with it. Use this when you need strict control
    | over which models can be referenced in polymorphic relationships and want
    | to prevent unmapped classes from being stored.
    |
    */

    'enforceMorphKeyMap' => [],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Configure event dispatching for Tracer operations. When enabled, Tracer
    | will dispatch events for revisions, staged changes, and approvals,
    | allowing you to hook into the lifecycle of tracked changes.
    |
    */

    'events' => [

        /*
        |--------------------------------------------------------------------------
        | Events Enabled
        |--------------------------------------------------------------------------
        |
        | Toggle event dispatching for all Tracer operations. When enabled,
        | events like RevisionCreated, StagedChangeCreated, and ApprovalRecorded
        | will be dispatched, allowing listeners to react to changes. Disable
        | this for performance in high-throughput scenarios where events aren't
        | needed.
        |
        */

        'enabled' => true,

    ],

];
