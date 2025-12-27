---
title: Staged Changes
description: Learn how to queue model changes for review and approval before they're persisted using Tracer's staged changes system.
---

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
