## Table of Contents

1. Getting Started (`docs/README.md`)
2. Basic Usage (`docs/basic-usage.md`)
3. Staged Changes (`docs/staged-changes.md`)
4. Approval Workflows (`docs/approval-workflows.md`)
5. Strategies (`docs/strategies.md`)
6. Advanced Usage (`docs/advanced-usage.md`)
Welcome to Tracer, a Laravel package for tracking model revisions and managing staged changes with approval workflows. This guide will help you install, configure, and start using Tracer in your application.

## Installation

Install Tracer via Composer:

```bash
composer require cline/tracer
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=tracer-config
```

This creates `config/tracer.php` with the following structure:

```php
return [
    'primary_key_type' => env('TRACER_PRIMARY_KEY_TYPE', 'id'),

    'morph_type' => env('TRACER_MORPH_TYPE', 'string'),

    'table_names' => [
        'revisions' => 'revisions',
        'staged_changes' => 'staged_changes',
        'staged_change_approvals' => 'staged_change_approvals',
    ],

    'diff_strategies' => [
        'snapshot' => SnapshotDiffStrategy::class,
        'attribute' => AttributeDiffStrategy::class,
    ],

    'default_diff_strategy' => SnapshotDiffStrategy::class,

    'approval_strategies' => [
        'simple' => SimpleApprovalStrategy::class,
        'quorum' => QuorumApprovalStrategy::class,
    ],

    'default_approval_strategy' => SimpleApprovalStrategy::class,

    'quorum' => [
        'approvals_required' => 2,
        'rejections_required' => 1,
    ],

    'untracked_attributes' => [
        'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
    ],

    'unstageable_attributes' => [
        'id', 'created_at', 'updated_at', 'deleted_at',
    ],
];
```

### Primary Key Types

Tracer supports three primary key types for its tables:

| Type | Description |
|------|-------------|
| `id` | Auto-incrementing integer (default) |
| `uuid` | UUID v4 strings |
| `ulid` | ULID strings (time-sortable) |

Set via environment variable:

```env
TRACER_PRIMARY_KEY_TYPE=ulid
```

### Morph Types

Configure how polymorphic IDs are stored:

| Type | Description |
|------|-------------|
| `string` | Standard string column (default) |
| `uuid` | UUID-specific column type |
| `ulid` | ULID-specific column type |

```env
TRACER_MORPH_TYPE=uuid
```

### Per-Model Configuration

Configure model-specific settings in `config/tracer.php`:

```php
'models' => [
    App\Models\Article::class => [
        'tracked_attributes' => ['title', 'content', 'status'],
        'untracked_attributes' => ['internal_notes'],
        'revision_diff_strategy' => AttributeDiffStrategy::class,
        'stageable_attributes' => ['title', 'content'],
        'unstageable_attributes' => ['admin_only'],
        'staged_diff_strategy' => SnapshotDiffStrategy::class,
        'approval_strategy' => QuorumApprovalStrategy::class,
    ],
],
```

Or configure at runtime via the `Tracer` facade:

```php
use Cline\Tracer\Tracer;

Tracer::configure(Article::class)
    ->trackAttributes(['title', 'content'])
    ->untrackAttributes(['internal_notes'])
    ->revisionDiffStrategy(AttributeDiffStrategy::class)
    ->stageableAttributes(['title', 'content'])
    ->approvalStrategy(QuorumApprovalStrategy::class);
```

## Database Setup

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=tracer-migrations
php artisan migrate
```

This creates three tables:

### `revisions` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | configurable | Primary key |
| `traceable_type` | string | Polymorphic model type |
| `traceable_id` | configurable | Polymorphic model ID |
| `version` | integer | Revision version number |
| `action` | string | created, updated, deleted, restored, reverted, force_deleted |
| `old_values` | json | Previous attribute values |
| `new_values` | json | New attribute values |
| `diff_strategy` | string | Strategy used to calculate diff |
| `causer_type` | string | User/entity who made the change |
| `causer_id` | configurable | ID of the causer |
| `metadata` | json | Additional context |

### `staged_changes` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | configurable | Primary key |
| `stageable_type` | string | Polymorphic model type |
| `stageable_id` | configurable | Polymorphic model ID |
| `original_values` | json | Current attribute values |
| `proposed_values` | json | Proposed new values |
| `diff_strategy` | string | Diff strategy identifier |
| `approval_strategy` | string | Approval workflow strategy |
| `status` | string | pending, approved, rejected, applied, cancelled |
| `reason` | text | Reason for the change |
| `rejection_reason` | text | Why the change was rejected |
| `approval_metadata` | json | Approval workflow data |
| `author_type` | string | User/entity proposing the change |
| `author_id` | configurable | ID of the author |
| `metadata` | json | Additional context |
| `applied_at` | timestamp | When the change was applied |

### `staged_change_approvals` Table

| Column | Type | Description |
|--------|------|-------------|
| `id` | configurable | Primary key |
| `staged_change_id` | configurable | Foreign key to staged_changes |
| `approver_type` | string | User/entity who approved/rejected |
| `approver_id` | configurable | ID of the approver |
| `approved` | boolean | true = approved, false = rejected |
| `comment` | text | Approval/rejection comment |
| `sequence` | integer | Order of approval |

## Quick Start: Revision Tracking

Add the `HasRevisions` trait to any model you want to track:

```php
use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Contracts\Traceable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements Traceable
{
    use HasRevisions;

    protected $fillable = ['title', 'content', 'status'];
}
```

That's it! Changes are automatically tracked:

```php
// Create - tracked automatically
$article = Article::create([
    'title' => 'Hello World',
    'content' => 'My first article',
    'status' => 'draft',
]);

// Update - tracked automatically
$article->update(['status' => 'published']);

// View revisions
$article->revisions; // Collection of all revisions
$article->latestRevision(); // Most recent revision

// Revert to previous version via facade
Tracer::revisions($article)->revertTo(1); // Revert to version 1
```

## Quick Start: Staged Changes

Add the `HasStagedChanges` trait for approval workflows:

```php
use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Concerns\HasStagedChanges;
use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Contracts\Traceable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements Traceable, Stageable
{
    use HasRevisions;
    use HasStagedChanges;

    protected $fillable = ['title', 'content', 'status'];
}
```

Stage changes for approval:

```php
use Cline\Tracer\Tracer;

// Stage a change
$stagedChange = Tracer::staging($article)->stage(
    ['title' => 'Updated Title'],
    'Fixing typo in title'
);

// Approve the change
Tracer::approve($stagedChange, auth()->user(), 'Looks good!');

// Apply approved changes
Tracer::staging($article)->applyApproved();

// Or reject
Tracer::reject($stagedChange, auth()->user(), 'Title too long');
```

## Using the Facade

Tracer provides a fluent facade API:

```php
use Cline\Tracer\Tracer;

// Work with revisions
$revisions = Tracer::revisions($article)->all();
$latest = Tracer::revisions($article)->latest();
Tracer::revertTo($article, 3);

// Work with staged changes
$staged = Tracer::staging($article)->pending();
Tracer::stage($article, ['title' => 'New Title'], 'Reason');
Tracer::approve($stagedChange, $approver);
Tracer::reject($stagedChange, $rejector, 'Not appropriate');
Tracer::apply($stagedChange);
```

## Understanding the Two Systems

Tracer provides two distinct but complementary systems:

### Revisions (Audit Trail)

- **Purpose**: Automatic history of all changes
- **When**: Changes tracked immediately on save
- **Use Case**: Audit logs, history viewing, reverting to past states
- **Trait**: `HasRevisions`

### Staged Changes (Approval Workflow)

- **Purpose**: Queue changes for review before persisting
- **When**: Changes held until explicitly approved and applied
- **Use Case**: Content moderation, maker-checker workflows, sensitive data changes
- **Trait**: `HasStagedChanges`

You can use either system alone or both together:

```php
// Only revisions (audit trail)
class AuditedModel extends Model implements Traceable
{
    use HasRevisions;
}

// Only staging (approval workflow without history)
class ModeratedModel extends Model implements Stageable
{
    use HasStagedChanges;
}

// Both systems (full audit trail + approval workflow)
class FullModel extends Model implements Traceable, Stageable
{
    use HasRevisions;
    use HasStagedChanges;
}
```

## Next Steps

Now that you have Tracer installed, explore more features:

- **[Basic Usage](basic-usage)** - Deep dive into revision tracking
- **[Staged Changes](staged-changes)** - Complete staging workflow guide
- **[Approval Workflows](approval-workflows)** - Configure approval strategies
- **[Strategies](strategies)** - Customize diff calculation
- **[Advanced Usage](advanced-usage)** - Events, custom strategies, and more

This guide covers the revision tracking system in detail, including configuration options, querying revisions, and reverting changes.

## Setting Up a Model

Add the `HasRevisions` trait and implement the `Traceable` interface:

```php
use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Contracts\Traceable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements Traceable
{
    use HasRevisions;

    protected $fillable = ['title', 'content', 'status', 'author_id'];
}
```

## Automatic Tracking

Once configured, Tracer automatically tracks:

| Event | Action Recorded | Old Values | New Values |
|-------|-----------------|------------|------------|
| `created` | `created` | Empty | All tracked attributes |
| `updated` | `updated` | Changed attributes (old) | Changed attributes (new) |
| `deleted` | `deleted` | All tracked attributes | Empty |
| `forceDeleted` | `force_deleted` | All tracked attributes | Empty |
| `restored` | `restored` | Empty | All tracked attributes |
| Revert | `reverted` | Current state | Reverted state |

```php
// All these are tracked automatically
$article = Article::create(['title' => 'Hello', 'content' => 'World']);
// Revision 1: action=created

$article->update(['title' => 'Hello World']);
// Revision 2: action=updated, old={title: 'Hello'}, new={title: 'Hello World'}

$article->delete();
// Revision 3: action=deleted
```

## Controlling Tracked Attributes

Configuration is managed via `config/tracer.php` or runtime registration, not on the model itself.

### Via Config File

```php
// config/tracer.php
'models' => [
    App\Models\Article::class => [
        'tracked_attributes' => ['title', 'content', 'status'],
        'untracked_attributes' => ['internal_notes'],
    ],
    App\Models\User::class => [
        'untracked_attributes' => ['password', 'api_token'],
    ],
],
```

### Via Runtime Registration

```php
use Cline\Tracer\Tracer;

// Track specific attributes only
Tracer::configure(Article::class)
    ->trackAttributes(['title', 'content', 'status']);

// Exclude specific attributes
Tracer::configure(User::class)
    ->untrackAttributes(['password', 'api_token']);
```

### Global Untracked Attributes

Configure in `config/tracer.php`:

```php
'untracked_attributes' => [
    'id',
    'created_at',
    'updated_at',
    'deleted_at',
    'remember_token',
    'password',        // Add custom global exclusions
    'api_token',
],
```

## Querying Revisions

### Get All Revisions

```php
// Via relationship (ordered by version descending)
$revisions = $article->revisions;

// Via facade
$revisions = Tracer::revisions($article)->all();
```

### Get Latest Revision

```php
$latest = $article->latestRevision();
// or
$latest = Tracer::revisions($article)->latest();
```

### Get Specific Version

```php
$revision = $article->getRevision(3); // Get version 3
```

### Filter by Action

```php
use Cline\Tracer\Enums\RevisionAction;

$updates = $article->revisions()
    ->where('action', RevisionAction::Updated)
    ->get();
```

### Filter by Causer

```php
// Revisions made by a specific user
$userRevisions = $article->revisions()
    ->where('causer_type', User::class)
    ->where('causer_id', $userId)
    ->get();
```

### Filter by Date Range

```php
$recentRevisions = $article->revisions()
    ->where('created_at', '>=', now()->subDays(7))
    ->get();
```

## Working with Revision Data

### Access Changed Values

```php
$revision = $article->latestRevision();

// Get all old values
$oldValues = $revision->old_values;
// ['title' => 'Old Title']

// Get all new values
$newValues = $revision->new_values;
// ['title' => 'New Title']

// Check if specific attribute changed
if ($revision->hasChangedAttribute('title')) {
    $oldTitle = $revision->getOldValue('title');
    $newTitle = $revision->getNewValue('title');
}
```

### Get Human-Readable Description

```php
$descriptions = $revision->describe();
// [
//     'title' => 'Changed from "Old Title" to "New Title"',
//     'status' => 'Set to "published"',
// ]
```

### Access Metadata

```php
$revision = $article->latestRevision();

// Who made the change
$causer = $revision->causer; // User model (polymorphic)

// When
$when = $revision->created_at;

// Action type
$action = $revision->action; // RevisionAction enum

// Version number
$version = $revision->version; // int
```

## Reverting Changes

### Revert to Specific Version

```php
use Cline\Tracer\Tracer;

// Revert to version 3
Tracer::revisions($article)->revertTo(3);

// Or via the TracerManager directly
Tracer::revertTo($article, 3);
```

### Revert to Revision Model

```php
$targetRevision = $article->revisions()->where('version', 3)->first();
Tracer::revisions($article)->revertTo($targetRevision);
```

### Revert by Revision ID

```php
Tracer::revisions($article)->revertTo('01HQ4XYZABC...'); // ULID/UUID
```

### What Happens During Revert

1. Tracer reconstructs the model state at the target revision
2. Applies those values to the current model
3. Saves the model (without tracking this save)
4. Creates a new "reverted" revision recording the change

```php
$article->revertToRevision(2);

$latest = $article->latestRevision();
$latest->action; // RevisionAction::Reverted
$latest->metadata; // ['reverted_to_version' => 2]
```

## Disabling Tracking Temporarily

### For a Specific Operation

```php
use Cline\Tracer\Tracer;

Tracer::revisions($article)->withoutTracking(function () use ($article) {
    $article->update(['view_count' => $article->view_count + 1]);
});
```

### Manual Control

```php
use Cline\Tracer\Tracer;

Tracer::revisions($article)->disableTracking();
$article->update(['internal_notes' => 'Not tracked']);
Tracer::revisions($article)->enableTracking();
```

### Disable for Instance Lifetime

```php
use Cline\Tracer\Tracer;

$article = Article::find(1);
Tracer::revisions($article)->disableTracking();

// All changes to this instance are untracked
$article->update(['status' => 'archived']);
$article->update(['title' => 'New Title']);
```

## Custom Causer Resolution

By default, Tracer uses the authenticated user as the causer via the `AuthCauserResolver`. You can create a custom resolver:

```php
use Cline\Tracer\Contracts\CauserResolver;
use Illuminate\Database\Eloquent\Model;

class CustomCauserResolver implements CauserResolver
{
    public function resolve(): ?Model
    {
        // Use system user for automated changes
        if (app()->runningInConsole()) {
            return User::where('email', 'system@example.com')->first();
        }

        // Use API token owner for API requests
        if (request()->bearerToken()) {
            return PersonalAccessToken::findToken(request()->bearerToken())?->tokenable;
        }

        return auth()->user();
    }
}
```

Register in `config/tracer.php`:

```php
'causer_resolver' => CustomCauserResolver::class,
```

## Custom Diff Strategy Per Model

Configure via `config/tracer.php`:

```php
'models' => [
    App\Models\Article::class => [
        'revision_diff_strategy' => AttributeDiffStrategy::class,
    ],
],
```

Or at runtime:

```php
use Cline\Tracer\Tracer;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;

Tracer::configure(Article::class)
    ->revisionDiffStrategy(AttributeDiffStrategy::class);
```

## SoftDeletes Integration

Tracer automatically detects soft deletes:

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model implements Traceable
{
    use HasRevisions;
    use SoftDeletes;
}
```

| Operation | Recorded Action |
|-----------|-----------------|
| `$article->delete()` | `deleted` |
| `$article->forceDelete()` | `force_deleted` |
| `$article->restore()` | `restored` |

## Performance Considerations

### Eager Loading

```php
// Load revisions with articles
$articles = Article::with('revisions')->get();

// Load only recent revisions
$articles = Article::with(['revisions' => function ($query) {
    $query->where('created_at', '>=', now()->subMonth())->limit(10);
}])->get();
```

### Indexing

The migrations include indexes on:
- `traceable_type` + `traceable_id` (composite)
- `version`
- `action`
- `causer_type` + `causer_id` (composite)
- `created_at`

### Pruning Old Revisions

```php
// Delete revisions older than 1 year
Revision::where('created_at', '<', now()->subYear())->delete();

// Keep only last 100 revisions per model
$article->revisions()
    ->orderByDesc('version')
    ->skip(100)
    ->take(PHP_INT_MAX)
    ->delete();
```

## Events

Tracer dispatches events for each revision:

```php
use Cline\Tracer\Events\RevisionCreated;

class RevisionListener
{
    public function handle(RevisionCreated $event): void
    {
        $revision = $event->revision;
        $model = $revision->traceable;

        // Send notification, log to external system, etc.
        Log::info("Revision {$revision->version} created for {$model->getKey()}");
    }
}
```

Register in `EventServiceProvider`:

```php
protected $listen = [
    RevisionCreated::class => [
        RevisionListener::class,
    ],
];
```

## Next Steps

- **[Staged Changes](staged-changes)** - Add approval workflows
- **[Strategies](strategies)** - Customize how diffs are calculated
- **[Advanced Usage](advanced-usage)** - Events, custom strategies, and more

This guide covers the staged changes system, which allows you to queue model changes for review and approval before they're persisted.

## When to Use Staged Changes

Staged changes are ideal for:

- **Content moderation**: Review user-submitted content before publishing
- **Maker-checker workflows**: Require approval for sensitive data changes
- **Compliance**: Audit trail of proposed vs. approved changes
- **Collaborative editing**: Multiple reviewers for important updates

## Setting Up a Model

Add the `HasStagedChanges` trait and implement the `Stageable` interface:

```php
use Cline\Tracer\Concerns\HasStagedChanges;
use Cline\Tracer\Contracts\Stageable;
use Illuminate\Database\Eloquent\Model;

class Article extends Model implements Stageable
{
    use HasStagedChanges;

    protected $fillable = ['title', 'content', 'status'];
}
```

For both revision tracking and staged changes:

```php
use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Concerns\HasStagedChanges;
use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Contracts\Traceable;

class Article extends Model implements Traceable, Stageable
{
    use HasRevisions;
    use HasStagedChanges;
}
```

## Staging Changes

All staging operations use the `Tracer` facade:

### Basic Staging

```php
use Cline\Tracer\Tracer;

$article = Article::find(1);

$stagedChange = Tracer::staging($article)->stage([
    'title' => 'Updated Title',
    'content' => 'Updated content here...',
]);
```

### With a Reason

```php
$stagedChange = Tracer::staging($article)->stage(
    ['title' => 'Fixed Typo in Title'],
    'Correcting spelling mistake reported by user'
);
```

### Via TracerManager Directly

```php
use Cline\Tracer\Tracer;

$stagedChange = Tracer::stage($article, [
    'title' => 'New Title',
], 'Marketing requested title change');
```

## Staged Change Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                         STAGED CHANGE                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────┐     ┌──────────┐     ┌─────────┐                 │
│   │ Pending │────▶│ Approved │────▶│ Applied │                 │
│   └────┬────┘     └──────────┘     └─────────┘                 │
│        │                                                        │
│        │          ┌──────────┐                                  │
│        ├─────────▶│ Rejected │                                  │
│        │          └──────────┘                                  │
│        │                                                        │
│        │          ┌───────────┐                                 │
│        └─────────▶│ Cancelled │                                 │
│                   └───────────┘                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

| Status | Description |
|--------|-------------|
| `pending` | Awaiting approval (mutable) |
| `approved` | Ready to apply (can be applied) |
| `rejected` | Denied (terminal) |
| `applied` | Changes persisted to model (terminal) |
| `cancelled` | Withdrawn (terminal) |

## Approving and Rejecting

### Simple Approval

```php
use Cline\Tracer\Tracer;

// Approve
Tracer::approve($stagedChange, auth()->user(), 'Looks good!');

// Reject
Tracer::reject($stagedChange, auth()->user(), 'Content violates guidelines');
```

### Via Conductor

```php
$staging = Tracer::staging($article);

// Get pending changes
$pending = $staging->pending();

// Approve specific change
$staging->approve($stagedChange, $approver, 'Approved for publication');

// Reject with reason
$staging->reject($stagedChange, $rejector, 'Needs more detail');
```

## Applying Changes

Once approved, changes must be explicitly applied:

### Apply Single Change

```php
$stagedChange->apply(auth()->user());
```

### Apply All Approved Changes

```php
use Cline\Tracer\Tracer;

$appliedCount = Tracer::staging($article)->applyApproved(auth()->user());
// Returns number of staged changes applied
```

### Via TracerManager

```php
Tracer::apply($stagedChange, auth()->user());
```

## Querying Staged Changes

### Via Relationships (read-only)

```php
// All staged changes via relationship
$all = $article->stagedChanges;

// Pending only via relationship
$pending = $article->pendingStagedChanges()->get();

// Approved only via relationship
$approved = $article->approvedStagedChanges()->get();
```

### Via Conductor (recommended)

```php
use Cline\Tracer\Tracer;

$staging = Tracer::staging($article);

// Check for pending
if ($staging->hasPending()) {
    // Show "pending review" indicator
}

// Check for approved
if ($staging->hasApproved()) {
    // Show "ready to apply" indicator
}
```

### Global Queries

```php
use Cline\Tracer\Tracer;

// All pending changes across all models
$allPending = Tracer::allPendingStagedChanges();

// All approved changes ready to apply
$allApproved = Tracer::allApprovedStagedChanges();
```

### Via Conductor

```php
$staging = Tracer::staging($article);

$pending = $staging->pending();
$approved = $staging->approved();
$all = $staging->all();
```

## Working with Staged Change Data

### Access Proposed Values

```php
$stagedChange->proposed_values;
// ['title' => 'New Title', 'content' => 'New content']

$stagedChange->getProposedValue('title');
// 'New Title'

$stagedChange->getProposedValue('missing_key', 'default');
// 'default'
```

### Access Original Values

```php
$stagedChange->original_values;
// ['title' => 'Old Title', 'content' => 'Old content']

$stagedChange->getOriginalValue('title');
// 'Old Title'
```

### Check What Would Change

```php
$stagedChange->getChangedAttributeKeys();
// ['title', 'content']

$stagedChange->wouldChange('title');    // true
$stagedChange->wouldChange('status');   // false
```

### Get Human-Readable Descriptions

```php
use Cline\Tracer\Tracer;

$diffStrategy = Tracer::resolveDiffStrategy($stagedChange->diff_strategy);
$descriptions = $diffStrategy->describe($stagedChange->proposed_values);
// [
//     'title' => 'Changed from "Old Title" to "New Title"',
//     'content' => 'Changed from "..." to "..."',
// ]
```

## Modifying Staged Changes

### Update Proposed Values

Only pending changes can be modified:

```php
$stagedChange->updateProposedValues([
    'title' => 'Even Newer Title',
]);
```

This merges with existing proposed values.

### Cancel a Staged Change

```php
$stagedChange->cancel();
```

### Cancel All Pending

```php
use Cline\Tracer\Tracer;

$cancelled = Tracer::staging($article)->cancelPending();
// Returns number cancelled
```

## Controlling Stageable Attributes

Configuration is managed via `config/tracer.php` or runtime registration, not on the model itself.

### Via Config File

```php
// config/tracer.php
'models' => [
    App\Models\Article::class => [
        'stageable_attributes' => ['title', 'content'],
        'unstageable_attributes' => ['internal_notes', 'admin_only'],
    ],
],
```

### Via Runtime Registration

```php
use Cline\Tracer\Tracer;

// Only allow staging specific attributes
Tracer::configure(Article::class)
    ->stageableAttributes(['title', 'content']);

// Exclude specific attributes from staging
Tracer::configure(Article::class)
    ->unstageableAttributes(['internal_notes', 'admin_only']);
```

### Global Unstageable Attributes

Configure in `config/tracer.php`:

```php
'unstageable_attributes' => [
    'id',
    'created_at',
    'updated_at',
    'deleted_at',
],
```

## Author Resolution

By default, the authenticated user is recorded as the author via the configured `CauserResolver`:

```php
use Cline\Tracer\Tracer;

$stagedChange = Tracer::staging($article)->stage(['title' => 'New']);
$stagedChange->author; // The authenticated user
```

### Custom Author Resolution

Create a custom `CauserResolver` to customize how authors are resolved:

```php
use Cline\Tracer\Contracts\CauserResolver;
use Illuminate\Database\Eloquent\Model;

class CustomCauserResolver implements CauserResolver
{
    public function resolve(): ?Model
    {
        // Use API client for API requests
        if (request()->hasHeader('X-API-Key')) {
            return ApiClient::findByKey(request()->header('X-API-Key'));
        }

        return auth()->user();
    }
}
```

Register in `config/tracer.php`:

```php
'causer_resolver' => CustomCauserResolver::class,
```

## Approval Metadata

Each staged change tracks approval workflow data:

```php
$stagedChange->approval_metadata;
// For simple approval:
// [
//     'approved_by_type' => 'App\\Models\\User',
//     'approved_by_id' => 5,
//     'approved_at' => '2024-01-15T10:30:00+00:00',
// ]

// For quorum approval:
// [
//     'quorum_reached' => true,
//     'approvals_required' => 2,
//     'approvals_received' => 2,
//     'approved_at' => '2024-01-15T10:30:00+00:00',
// ]
```

## Approval Records

Each approval/rejection is recorded individually:

```php
$stagedChange->approvals;
// Collection of StagedChangeApproval models

foreach ($stagedChange->approvals as $approval) {
    $approval->approver;      // User model
    $approval->approved;      // true/false
    $approval->comment;       // Approval/rejection comment
    $approval->sequence;      // Order of approval
    $approval->created_at;    // When they voted
}
```

## Events

Tracer dispatches events throughout the staged change lifecycle:

```php
use Cline\Tracer\Events\StagedChangeCreated;
use Cline\Tracer\Events\StagedChangeApproved;
use Cline\Tracer\Events\StagedChangeRejected;
use Cline\Tracer\Events\StagedChangeApplied;

// In EventServiceProvider
protected $listen = [
    StagedChangeCreated::class => [
        NotifyReviewersListener::class,
    ],
    StagedChangeApproved::class => [
        NotifyAuthorApprovedListener::class,
    ],
    StagedChangeRejected::class => [
        NotifyAuthorRejectedListener::class,
    ],
    StagedChangeApplied::class => [
        LogChangeAppliedListener::class,
    ],
];
```

## Error Handling

### Cannot Modify Non-Mutable Changes

```php
use Cline\Tracer\Exceptions\CannotModifyStagedChangeException;

try {
    $approvedChange->updateProposedValues(['title' => 'New']);
} catch (CannotModifyStagedChangeException $e) {
    // Change is already approved/rejected/applied
}
```

### Cannot Apply Non-Approved Changes

```php
use Cline\Tracer\Exceptions\CannotApplyStagedChangeException;

try {
    $pendingChange->apply();
} catch (CannotApplyStagedChangeException $e) {
    // Change must be approved first
}
```

### Target Model Not Found

```php
try {
    $stagedChange->apply();
} catch (CannotApplyStagedChangeException $e) {
    // The target model was deleted
}
```

## Integration with Revisions

When using both traits, applying a staged change creates a revision:

```php
class Article extends Model implements Traceable, Stageable
{
    use HasRevisions;
    use HasStagedChanges;
}

// Stage a change
$staged = Tracer::staging($article)->stage(['title' => 'New Title']);

// Approve and apply
Tracer::approve($staged, $admin);
$staged->apply($admin);

// A revision is automatically created
$revision = $article->latestRevision();
$revision->action; // RevisionAction::Updated
$revision->new_values; // ['title' => 'New Title']
```

## Next Steps

- **[Approval Workflows](approval-workflows)** - Configure approval strategies
- **[Strategies](strategies)** - Customize diff calculation
- **[Advanced Usage](advanced-usage)** - Events, custom strategies, and more

This guide covers the approval system for staged changes, including built-in strategies and creating custom workflows.

## Approval Strategies

Tracer uses strategies to determine how staged changes are approved. Each strategy defines:

- Who can approve/reject
- How many approvals are needed
- When the change is considered fully approved

## Built-in Strategies

### Simple Approval Strategy

Single approver workflow - one approval is sufficient.

```php
// config/tracer.php
'default_approval_strategy' => SimpleApprovalStrategy::class,
```

**Behavior:**
- Any authorized user can approve
- One approval marks the change as approved
- One rejection marks the change as rejected

```php
use Cline\Tracer\Tracer;

$staged = $article->stageChanges(['title' => 'New Title']);

// Single approval is enough
Tracer::approve($staged, $admin, 'Approved');

$staged->status; // StagedChangeStatus::Approved
```

### Quorum Approval Strategy

Multiple approvers required - configurable threshold.

```php
// config/tracer.php
'default_approval_strategy' => QuorumApprovalStrategy::class,

'quorum' => [
    'approvals_required' => 2,   // Need 2 approvals
    'rejections_required' => 1,  // 1 rejection blocks
],
```

**Behavior:**
- Requires N approvals before approved
- M rejections will reject the change
- Each user can only vote once
- Tracks all individual votes

```php
$staged = $article->stageChanges(['title' => 'New Title']);

// First approval - not enough
Tracer::approve($staged, $admin1, 'Looks good');
$staged->status; // StagedChangeStatus::Pending

// Second approval - quorum reached
Tracer::approve($staged, $admin2, 'Also approved');
$staged->status; // StagedChangeStatus::Approved
```

## Per-Model Strategy Configuration

Override the default strategy for specific models:

```php
use Cline\Tracer\Strategies\Approval\QuorumApprovalStrategy;

class SensitiveDocument extends Model implements Stageable
{
    use HasStagedChanges;

    // Use quorum for sensitive documents
    protected string $approvalStrategy = QuorumApprovalStrategy::class;
}
```

Or via method:

```php
class SensitiveDocument extends Model implements Stageable
{
    use HasStagedChanges;

    public function getApprovalStrategy(): ?string
    {
        // Dynamic strategy based on document type
        if ($this->classification === 'top-secret') {
            return QuorumApprovalStrategy::class;
        }

        return null; // Use default
    }
}
```

## Working with Approvals

### Check Approval Status

```php
use Cline\Tracer\Tracer;

$strategy = Tracer::resolveApprovalStrategy($staged->approval_strategy);
$status = $strategy->status($staged);

// [
//     'strategy' => 'quorum',
//     'status' => 'pending',
//     'approvals_required' => 2,
//     'approvals_received' => 1,
//     'rejections_required' => 1,
//     'rejections_received' => 0,
//     'remaining_approvals' => 1,
//     'is_approved' => false,
//     'is_rejected' => false,
//     'can_be_approved' => true,
// ]
```

### Check If User Can Approve

```php
$strategy = Tracer::resolveApprovalStrategy($staged->approval_strategy);

if ($strategy->canApprove($staged, $currentUser)) {
    // Show approve button
}

if ($strategy->canReject($staged, $currentUser)) {
    // Show reject button
}
```

### View Approval History

```php
foreach ($staged->approvals as $approval) {
    echo $approval->approver->name;          // "John Doe"
    echo $approval->approved ? '✓' : '✗';    // "✓"
    echo $approval->comment;                 // "Looks good to me"
    echo $approval->created_at;              // "2024-01-15 10:30:00"
}
```

## Custom Approval Strategies

Create custom strategies for complex workflows:

### Step 1: Create the Strategy Class

```php
namespace App\Strategies\Approval;

use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Illuminate\Database\Eloquent\Model;

class HierarchicalApprovalStrategy implements ApprovalStrategy
{
    public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool
    {
        if ($stagedChange->status !== StagedChangeStatus::Pending) {
            return false;
        }

        if ($approver === null) {
            return false;
        }

        // Only managers can approve
        return $approver->hasRole('manager');
    }

    public function canReject(StagedChange $stagedChange, ?Model $rejector = null): bool
    {
        if ($stagedChange->status !== StagedChangeStatus::Pending) {
            return false;
        }

        if ($rejector === null) {
            return false;
        }

        // Anyone in the team can reject
        $stageable = $stagedChange->stageable;
        return $rejector->team_id === $stageable->team_id;
    }

    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        if (!$this->canApprove($stagedChange, $approver)) {
            return false;
        }

        // Record approval
        $stagedChange->approvals()->create([
            'approved' => true,
            'comment' => $comment,
            'approver_type' => $approver?->getMorphClass(),
            'approver_id' => $approver?->getKey(),
            'sequence' => 1,
        ]);

        // Check if approver is senior enough
        if ($approver->level >= 3) {
            // Senior managers can approve directly
            $stagedChange->status = StagedChangeStatus::Approved;
            $stagedChange->approval_metadata = [
                'approved_by_senior' => true,
                'approver_level' => $approver->level,
            ];
            $stagedChange->save();
            return true;
        }

        // Junior managers need another approval
        $approvals = $stagedChange->approvals()->where('approved', true)->count();
        if ($approvals >= 2) {
            $stagedChange->status = StagedChangeStatus::Approved;
            $stagedChange->save();
            return true;
        }

        return false;
    }

    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool
    {
        if (!$this->canReject($stagedChange, $rejector)) {
            return false;
        }

        $stagedChange->approvals()->create([
            'approved' => false,
            'comment' => $reason,
            'approver_type' => $rejector?->getMorphClass(),
            'approver_id' => $rejector?->getKey(),
            'sequence' => $stagedChange->approvals()->count() + 1,
        ]);

        $stagedChange->status = StagedChangeStatus::Rejected;
        $stagedChange->rejection_reason = $reason;
        $stagedChange->save();

        return true;
    }

    public function status(StagedChange $stagedChange): array
    {
        return [
            'strategy' => $this->identifier(),
            'status' => $stagedChange->status->value,
            'requires_senior' => true,
            'approvals_received' => $stagedChange->approvals()->where('approved', true)->count(),
        ];
    }

    public function identifier(): string
    {
        return 'hierarchical';
    }
}
```

### Step 2: Register the Strategy

```php
// config/tracer.php
'approval_strategies' => [
    'simple' => SimpleApprovalStrategy::class,
    'quorum' => QuorumApprovalStrategy::class,
    'hierarchical' => \App\Strategies\Approval\HierarchicalApprovalStrategy::class,
],
```

Or at runtime:

```php
use Cline\Tracer\Tracer;

// In a service provider
Tracer::registerApprovalStrategy('hierarchical', HierarchicalApprovalStrategy::class);
```

### Step 3: Use the Strategy

```php
class TeamDocument extends Model implements Stageable
{
    use HasStagedChanges;

    protected string $approvalStrategy = HierarchicalApprovalStrategy::class;
}
```

## Dynamic Approval Requirements

Override quorum requirements per staged change:

```php
$staged = $article->stageChanges(['title' => 'New Title']);

// Override for this specific change
$staged->approval_metadata = [
    'approvals_required' => 3,  // Need 3 instead of default 2
    'rejections_required' => 2, // Need 2 rejections to block
];
$staged->save();
```

The quorum strategy checks metadata first, then falls back to config.

## Authorization Integration

Integrate with Laravel's authorization:

```php
// In AuthServiceProvider or a Policy
Gate::define('approve-staged-change', function (User $user, StagedChange $staged) {
    // Only document owners and admins can approve
    $stageable = $staged->stageable;

    if ($stageable instanceof Document) {
        return $user->id === $stageable->owner_id || $user->isAdmin();
    }

    return $user->isAdmin();
});
```

Use in your strategy:

```php
public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool
{
    if ($stagedChange->status !== StagedChangeStatus::Pending) {
        return false;
    }

    if ($approver === null) {
        return false;
    }

    return Gate::forUser($approver)->allows('approve-staged-change', $stagedChange);
}
```

## Workflow Examples

### Content Moderation

```php
class ModerationApprovalStrategy implements ApprovalStrategy
{
    public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool
    {
        return $approver?->hasPermission('moderate-content') ?? false;
    }

    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        // ... standard approval logic ...

        // Log for compliance
        ModerationLog::create([
            'action' => 'approved',
            'staged_change_id' => $stagedChange->id,
            'moderator_id' => $approver->id,
            'comment' => $comment,
        ]);

        return true;
    }
}
```

### Two-Phase Approval

```php
class TwoPhaseApprovalStrategy implements ApprovalStrategy
{
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        $metadata = $stagedChange->approval_metadata ?? [];
        $phase = $metadata['phase'] ?? 1;

        if ($phase === 1) {
            // First phase: technical review
            if (!$approver->hasRole('tech-reviewer')) {
                return false;
            }

            $stagedChange->approvals()->create([
                'approved' => true,
                'comment' => $comment,
                'approver_type' => $approver->getMorphClass(),
                'approver_id' => $approver->getKey(),
                'sequence' => 1,
            ]);

            $stagedChange->approval_metadata = [
                'phase' => 2,
                'tech_approved_by' => $approver->id,
            ];
            $stagedChange->save();

            return false; // Not fully approved yet
        }

        if ($phase === 2) {
            // Second phase: business review
            if (!$approver->hasRole('business-reviewer')) {
                return false;
            }

            $stagedChange->approvals()->create([
                'approved' => true,
                'comment' => $comment,
                'approver_type' => $approver->getMorphClass(),
                'approver_id' => $approver->getKey(),
                'sequence' => 2,
            ]);

            $stagedChange->status = StagedChangeStatus::Approved;
            $stagedChange->approval_metadata = array_merge($metadata, [
                'business_approved_by' => $approver->id,
            ]);
            $stagedChange->save();

            return true;
        }

        return false;
    }

    public function identifier(): string
    {
        return 'two-phase';
    }
}
```

## Next Steps

- **[Strategies](strategies)** - Customize diff calculation
- **[Advanced Usage](advanced-usage)** - Events, custom strategies, and more

This guide covers diff strategies, which control how model changes are calculated and stored.

## What Are Diff Strategies?

Diff strategies determine:

- **How changes are calculated** between old and new values
- **How changes are stored** in the database
- **How changes are applied** when reverting or applying staged changes
- **How changes are described** in human-readable format

## Built-in Strategies

### Snapshot Diff Strategy

Stores complete old and new values for all changed attributes.

```php
// config/tracer.php
'default_diff_strategy' => SnapshotDiffStrategy::class,
```

**Storage Format:**

```php
$revision->old_values = ['title' => 'Old Title', 'content' => 'Old content'];
$revision->new_values = ['title' => 'New Title', 'content' => 'New content'];
```

**Pros:**
- Simple and reliable
- Easy to understand and debug
- Straightforward reversion

**Cons:**
- Uses more storage for large text fields
- Stores complete values even for small changes

### Attribute Diff Strategy

Stores per-attribute change metadata with type information.

```php
// config/tracer.php
'default_diff_strategy' => AttributeDiffStrategy::class,
```

**Storage Format:**

```php
$revision->old_values = [
    'title' => ['value' => 'Old Title', 'type' => 'string'],
    'count' => ['value' => 5, 'type' => 'integer'],
];
$revision->new_values = [
    'title' => ['value' => 'New Title', 'type' => 'string'],
    'count' => ['value' => 10, 'type' => 'integer'],
];
```

**Pros:**
- Preserves type information
- Better for complex data structures

**Cons:**
- Slightly more complex storage format
- More overhead for simple changes

## Per-Model Strategy Configuration

Configuration is managed via `config/tracer.php` or runtime registration, not on the model itself.

### Via Config File

```php
// config/tracer.php
'models' => [
    App\Models\Article::class => [
        'revision_diff_strategy' => AttributeDiffStrategy::class,
        'staged_diff_strategy' => SnapshotDiffStrategy::class,
    ],
],
```

### Via Runtime Registration

```php
use Cline\Tracer\Tracer;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;

// For revisions
Tracer::configure(Article::class)
    ->revisionDiffStrategy(AttributeDiffStrategy::class);

// For staged changes
Tracer::configure(Article::class)
    ->stagedDiffStrategy(AttributeDiffStrategy::class);
```

## Creating Custom Diff Strategies

### Step 1: Implement the Interface

```php
namespace App\Strategies\Diff;

use Cline\Tracer\Contracts\DiffStrategy;
use Illuminate\Database\Eloquent\Model;

class JsonPatchDiffStrategy implements DiffStrategy
{
    /**
     * Calculate the diff between old and new values.
     */
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $patches = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $patches[$key] = [
                'op' => $oldValue === null ? 'add' : 'replace',
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Handle removed keys
        foreach ($oldValues as $key => $oldValue) {
            if (!array_key_exists($key, $newValues)) {
                $patches[$key] = [
                    'op' => 'remove',
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $patches;
    }

    /**
     * Apply a stored diff to reconstruct values.
     */
    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $result = $currentValues;

        foreach ($diff as $key => $patch) {
            $targetValue = $reverse ? $patch['old'] : $patch['new'];

            if ($targetValue === null && isset($result[$key])) {
                unset($result[$key]);
            } else {
                $result[$key] = $targetValue;
            }
        }

        return $result;
    }

    /**
     * Get human-readable descriptions of changes.
     */
    public function describe(array $diff): array
    {
        $descriptions = [];

        foreach ($diff as $key => $patch) {
            $op = $patch['op'];

            $descriptions[$key] = match ($op) {
                'add' => sprintf('Added: "%s"', $this->format($patch['new'])),
                'remove' => sprintf('Removed: "%s"', $this->format($patch['old'])),
                'replace' => sprintf('Changed from "%s" to "%s"',
                    $this->format($patch['old']),
                    $this->format($patch['new'])
                ),
                default => 'Unknown change',
            };
        }

        return $descriptions;
    }

    /**
     * Get the unique identifier for this strategy.
     */
    public function identifier(): string
    {
        return 'json-patch';
    }

    private function format(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
```

### Step 2: Register the Strategy

```php
// config/tracer.php
'diff_strategies' => [
    'snapshot' => SnapshotDiffStrategy::class,
    'attribute' => AttributeDiffStrategy::class,
    'json-patch' => \App\Strategies\Diff\JsonPatchDiffStrategy::class,
],
```

Or at runtime:

```php
use Cline\Tracer\Tracer;

Tracer::registerDiffStrategy('json-patch', JsonPatchDiffStrategy::class);
```

### Step 3: Use the Strategy

```php
class Article extends Model implements Traceable
{
    use HasRevisions;

    protected string $revisionDiffStrategy = JsonPatchDiffStrategy::class;
}
```

## Strategy Examples

### Compact Text Diff Strategy

For large text fields, store only the differences:

```php
use SebastianBergmann\Diff\Differ;

class CompactTextDiffStrategy implements DiffStrategy
{
    private Differ $differ;

    public function __construct()
    {
        $this->differ = new Differ();
    }

    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $result = [
            'old' => [],
            'new' => [],
            'text_diffs' => [],
        ];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // For large text, store diff instead of full values
            if (is_string($newValue) && strlen($newValue) > 1000) {
                $result['text_diffs'][$key] = $this->differ->diff(
                    (string) $oldValue,
                    $newValue
                );
            } else {
                $result['old'][$key] = $oldValue;
                $result['new'][$key] = $newValue;
            }
        }

        return $result;
    }

    public function identifier(): string
    {
        return 'compact-text';
    }

    // ... implement apply() and describe()
}
```

### Encrypted Diff Strategy

For sensitive data that should be encrypted at rest:

```php
use Illuminate\Support\Facades\Crypt;

class EncryptedDiffStrategy implements DiffStrategy
{
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        return [
            'old' => Crypt::encryptString(json_encode($oldValues)),
            'new' => Crypt::encryptString(json_encode($newValues)),
            'encrypted' => true,
        ];
    }

    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $targetKey = $reverse ? 'old' : 'new';
        $values = json_decode(Crypt::decryptString($diff[$targetKey]), true);

        return array_merge($currentValues, $values);
    }

    public function describe(array $diff): array
    {
        // Decrypt for description
        $old = json_decode(Crypt::decryptString($diff['old']), true);
        $new = json_decode(Crypt::decryptString($diff['new']), true);

        $descriptions = [];
        foreach (array_keys($new) as $key) {
            if (($old[$key] ?? null) !== $new[$key]) {
                $descriptions[$key] = 'Value changed (encrypted)';
            }
        }

        return $descriptions;
    }

    public function identifier(): string
    {
        return 'encrypted';
    }
}
```

### Semantic Diff Strategy

For JSON/array fields, provide detailed structure comparison:

```php
class SemanticDiffStrategy implements DiffStrategy
{
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $result = ['changes' => []];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if (is_array($newValue) && is_array($oldValue)) {
                // Deep comparison for arrays
                $result['changes'][$key] = $this->compareArrays($oldValue, $newValue);
            } else {
                $result['changes'][$key] = [
                    'type' => 'scalar',
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $result;
    }

    private function compareArrays(array $old, array $new): array
    {
        return [
            'type' => 'array',
            'added' => array_diff_key($new, $old),
            'removed' => array_diff_key($old, $new),
            'modified' => array_filter(
                array_intersect_key($new, $old),
                fn($v, $k) => $old[$k] !== $v,
                ARRAY_FILTER_USE_BOTH
            ),
        ];
    }

    public function identifier(): string
    {
        return 'semantic';
    }

    // ... implement apply() and describe()
}
```

## Resolving Strategies

### Get Strategy by Identifier

```php
use Cline\Tracer\Tracer;

$strategy = Tracer::resolveDiffStrategy('snapshot');
// Returns SnapshotDiffStrategy instance
```

### List Available Strategies

```php
$strategies = Tracer::getDiffStrategies();
// ['snapshot', 'attribute', 'json-patch', ...]
```

## Best Practices

1. **Choose the right strategy for your data**
   - Simple models: Use `snapshot` (default)
   - Large text fields: Consider custom compact strategy
   - Sensitive data: Use encrypted strategy

2. **Keep strategies consistent**
   - Don't change strategies on existing data
   - If you must change, plan a migration

3. **Test your custom strategies**
   - Verify `apply()` correctly reverses changes
   - Test with edge cases (nulls, empty arrays, etc.)

4. **Consider storage implications**
   - Large diffs impact database size
   - Consider pruning old revisions

## Next Steps

- **[Advanced Usage](advanced-usage)** - Events, pruning, and more

This guide covers advanced features including events, custom configurations, performance optimization, and integration patterns.

## Events

Tracer dispatches events throughout the lifecycle of revisions and staged changes.

### Available Events

| Event | When Dispatched |
|-------|-----------------|
| `RevisionCreated` | After a revision is created |
| `StagedChangeCreated` | After a staged change is created |
| `StagedChangeApproved` | After a staged change is approved |
| `StagedChangeRejected` | After a staged change is rejected |
| `StagedChangeApplied` | After a staged change is applied |

### Listening to Events

```php
// app/Providers/EventServiceProvider.php
use Cline\Tracer\Events\RevisionCreated;
use Cline\Tracer\Events\StagedChangeApproved;
use Cline\Tracer\Events\StagedChangeRejected;

protected $listen = [
    RevisionCreated::class => [
        SendAuditNotification::class,
    ],
    StagedChangeApproved::class => [
        NotifyAuthorOfApproval::class,
    ],
    StagedChangeRejected::class => [
        NotifyAuthorOfRejection::class,
    ],
];
```

### Event Payloads

```php
use Cline\Tracer\Events\RevisionCreated;

class SendAuditNotification
{
    public function handle(RevisionCreated $event): void
    {
        $revision = $event->revision;
        $model = $revision->traceable;
        $causer = $revision->causer;

        AuditLog::create([
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'action' => $revision->action->value,
            'user_id' => $causer?->id,
            'changes' => $revision->new_values,
        ]);
    }
}
```

### Disabling Events

```php
// config/tracer.php
'events' => [
    'enabled' => false,
],
```

Or temporarily:

```php
Config::set('tracer.events.enabled', false);
$model->update(['title' => 'Silent update']);
Config::set('tracer.events.enabled', true);
```

## Morph Key Maps

Configure polymorphic type mappings for cleaner database values:

```php
// config/tracer.php
'morphKeyMap' => [
    App\Models\User::class => 'user',
    App\Models\Article::class => 'article',
    App\Models\Document::class => 'document',
],
```

This stores `user` instead of `App\Models\User` in `traceable_type` columns.

### Enforce Morph Map

For strict control, use `enforceMorphKeyMap`:

```php
'enforceMorphKeyMap' => [
    App\Models\User::class => 'user',
    App\Models\Article::class => 'article',
],
```

This replaces Laravel's morph map entirely for Tracer operations.

## Conductors API

Conductors provide a fluent interface for complex operations.

### Revision Conductor

```php
use Cline\Tracer\Tracer;

$conductor = Tracer::revisions($article);

// Query revisions
$all = $conductor->all();
$latest = $conductor->latest();
$version3 = $conductor->version(3);

// Filter by action
$updates = $conductor->query()
    ->where('action', RevisionAction::Updated)
    ->get();

// Revert
$conductor->revertTo(3);
```

### Staging Conductor

```php
$conductor = Tracer::staging($article);

// Query staged changes
$pending = $conductor->pending();
$approved = $conductor->approved();
$all = $conductor->all();

// Actions
$conductor->approve($stagedChange, $approver, 'Comment');
$conductor->reject($stagedChange, $rejector, 'Reason');
$conductor->apply($stagedChange, $applier);
```

## Batch Operations

### Batch Revision Queries

```php
// Get revisions for multiple models
$articles = Article::whereIn('id', [1, 2, 3])->get();

$allRevisions = Revision::query()
    ->whereIn('traceable_id', $articles->pluck('id'))
    ->where('traceable_type', Article::class)
    ->orderByDesc('created_at')
    ->get();
```

### Batch Apply Staged Changes

```php
// Apply all approved changes across all models
$approved = StagedChange::query()
    ->where('status', StagedChangeStatus::Approved)
    ->get();

foreach ($approved as $staged) {
    try {
        $staged->apply(auth()->user());
    } catch (CannotApplyStagedChangeException $e) {
        Log::warning("Failed to apply staged change {$staged->id}: {$e->getMessage()}");
    }
}
```

## Performance Optimization

### Eager Loading

```php
// Load revisions with models
$articles = Article::with('revisions')->get();

// Load with limits
$articles = Article::with(['revisions' => function ($query) {
    $query->orderByDesc('version')->limit(5);
}])->get();
```

### Chunked Processing

```php
// Process revisions in chunks
Revision::where('created_at', '<', now()->subYear())
    ->chunkById(1000, function ($revisions) {
        foreach ($revisions as $revision) {
            // Archive to cold storage
        }
        $revisions->each->delete();
    });
```

### Indexing

Ensure proper indexes exist (included in migrations):

```sql
-- Revisions
CREATE INDEX idx_revisions_traceable ON revisions(traceable_type, traceable_id);
CREATE INDEX idx_revisions_causer ON revisions(causer_type, causer_id);
CREATE INDEX idx_revisions_created ON revisions(created_at);

-- Staged Changes
CREATE INDEX idx_staged_stageable ON staged_changes(stageable_type, stageable_id);
CREATE INDEX idx_staged_status ON staged_changes(status);
CREATE INDEX idx_staged_author ON staged_changes(author_type, author_id);
```

## Pruning and Maintenance

### Prune Old Revisions

```php
// Delete revisions older than 1 year
$deleted = Revision::where('created_at', '<', now()->subYear())->delete();

// Keep only last N revisions per model
$models = Revision::select('traceable_type', 'traceable_id')
    ->groupBy('traceable_type', 'traceable_id')
    ->get();

foreach ($models as $model) {
    Revision::where('traceable_type', $model->traceable_type)
        ->where('traceable_id', $model->traceable_id)
        ->orderByDesc('version')
        ->skip(100)
        ->take(PHP_INT_MAX)
        ->delete();
}
```

### Scheduled Cleanup

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        // Prune revisions older than config value
        $days = config('tracer.retention_days', 365);
        Revision::where('created_at', '<', now()->subDays($days))->delete();

        // Clean up orphaned staged changes
        StagedChange::whereIn('status', [
            StagedChangeStatus::Cancelled,
            StagedChangeStatus::Rejected,
        ])->where('updated_at', '<', now()->subDays(30))->delete();
    })->daily();
}
```

## Testing

### Using Array Driver (In-Memory)

For tests, disable database storage:

```php
// tests/TestCase.php
protected function setUp(): void
{
    parent::setUp();

    // Use in-memory storage for tests
    Config::set('tracer.events.enabled', false);
}
```

### Testing Revisions

```php
use Cline\Tracer\Database\Models\Revision;

test('creates revision on update', function () {
    $article = Article::create(['title' => 'Original']);

    $article->update(['title' => 'Updated']);

    expect($article->revisions)->toHaveCount(2);

    $latest = $article->latestRevision();
    expect($latest->action)->toBe(RevisionAction::Updated);
    expect($latest->old_values)->toHaveKey('title', 'Original');
    expect($latest->new_values)->toHaveKey('title', 'Updated');
});
```

### Testing Staged Changes

```php
use Cline\Tracer\Tracer;

test('staged change workflow', function () {
    $article = Article::create(['title' => 'Original']);
    $admin = User::factory()->create();

    // Stage
    $staged = $article->stageChanges(['title' => 'New Title']);
    expect($staged->status)->toBe(StagedChangeStatus::Pending);

    // Approve
    Tracer::approve($staged, $admin);
    $staged->refresh();
    expect($staged->status)->toBe(StagedChangeStatus::Approved);

    // Apply
    $staged->apply($admin);
    $article->refresh();
    expect($article->title)->toBe('New Title');
});
```

### Mocking Strategies

```php
test('uses custom approval strategy', function () {
    $strategy = Mockery::mock(ApprovalStrategy::class);
    $strategy->shouldReceive('identifier')->andReturn('mock');
    $strategy->shouldReceive('canApprove')->andReturn(true);
    $strategy->shouldReceive('approve')->andReturn(true);

    app()->instance('mock-strategy', $strategy);
    Tracer::registerApprovalStrategy('mock', 'mock-strategy');

    // Test with mocked strategy
});
```

## Integration Patterns

### With Laravel Nova

```php
// app/Nova/Article.php
class Article extends Resource
{
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Title'),

            // Show revision history
            HasMany::make('Revisions', 'revisions', Revision::class),

            // Show pending changes count
            Number::make('Pending Changes', function () {
                return $this->pendingStagedChanges()->count();
            })->onlyOnDetail(),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [
            new ApproveAllStagedChanges(),
            new RevertToRevision(),
        ];
    }
}
```

### With API Resources

```php
// app/Http/Resources/ArticleResource.php
class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,

            'revisions' => RevisionResource::collection(
                $this->whenLoaded('revisions')
            ),

            'pending_changes_count' => $this->when(
                $request->user()?->can('manage', $this->resource),
                fn() => $this->pendingStagedChanges()->count()
            ),

            'latest_revision' => new RevisionResource(
                $this->when($request->include_revision, $this->latestRevision())
            ),
        ];
    }
}
```

### With Webhooks

```php
// Notify external systems of changes
class SendWebhookOnRevision
{
    public function handle(RevisionCreated $event): void
    {
        $revision = $event->revision;
        $model = $revision->traceable;

        if (!$model instanceof WebhookEnabled) {
            return;
        }

        Http::post($model->webhook_url, [
            'event' => 'model.updated',
            'model_type' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
            'version' => $revision->version,
            'action' => $revision->action->value,
            'changes' => $revision->new_values,
            'changed_by' => $revision->causer?->email,
            'timestamp' => $revision->created_at->toIso8601String(),
        ]);
    }
}
```

### With Queues

```php
// Process staged changes asynchronously
class ProcessApprovedChanges implements ShouldQueue
{
    public function handle(): void
    {
        StagedChange::query()
            ->where('status', StagedChangeStatus::Approved)
            ->where('applied_at', null)
            ->each(function (StagedChange $staged) {
                try {
                    $staged->apply();
                } catch (CannotApplyStagedChangeException $e) {
                    Log::error("Failed to apply change {$staged->id}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
```

## Troubleshooting

### Revisions Not Being Created

1. Check the trait is added: `use HasRevisions`
2. Verify interface implemented: `implements Traceable`
3. Check tracking isn't disabled: `$model->revisionTrackingEnabled`
4. Ensure attributes aren't in untracked list

### Staged Changes Not Applying

1. Verify status is `Approved`
2. Check target model still exists
3. Verify diff strategy can apply changes
4. Check for validation errors on the model

### Strategy Not Found

```php
// Ensure strategy is registered
Tracer::getDiffStrategies();    // List available diff strategies
Tracer::getApprovalStrategies(); // List available approval strategies
```

### Memory Issues with Large Diffs

- Use chunked processing for batch operations
- Consider compact diff strategies for large text
- Prune old revisions regularly

## Configuration Reference

Full `config/tracer.php` options:

```php
return [
    // Primary key type: 'id', 'uuid', 'ulid'
    'primary_key_type' => 'id',

    // Morph type: 'string', 'uuid', 'ulid'
    'morph_type' => 'string',

    // Table names
    'table_names' => [
        'revisions' => 'revisions',
        'staged_changes' => 'staged_changes',
        'staged_change_approvals' => 'staged_change_approvals',
    ],

    // Diff strategies
    'diff_strategies' => [
        'snapshot' => SnapshotDiffStrategy::class,
        'attribute' => AttributeDiffStrategy::class,
    ],
    'default_diff_strategy' => SnapshotDiffStrategy::class,

    // Approval strategies
    'approval_strategies' => [
        'simple' => SimpleApprovalStrategy::class,
        'quorum' => QuorumApprovalStrategy::class,
    ],
    'default_approval_strategy' => SimpleApprovalStrategy::class,

    // Quorum settings
    'quorum' => [
        'approvals_required' => 2,
        'rejections_required' => 1,
    ],

    // Attribute exclusions
    'untracked_attributes' => [
        'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
    ],
    'unstageable_attributes' => [
        'id', 'created_at', 'updated_at', 'deleted_at',
    ],

    // Morph maps
    'morphKeyMap' => [],
    'enforceMorphKeyMap' => [],

    // Events
    'events' => [
        'enabled' => true,
    ],
];
```
