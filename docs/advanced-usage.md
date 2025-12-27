---
title: Advanced Usage
description: Advanced features including events, custom configurations, performance optimization, and integration patterns for Tracer.
---

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
