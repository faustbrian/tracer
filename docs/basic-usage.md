---
title: Basic Usage
description: Deep dive into the revision tracking system, including configuration options, querying revisions, and reverting changes.
---

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
