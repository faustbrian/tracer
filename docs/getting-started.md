---
title: Getting Started
description: Install, configure, and start using Tracer to track model revisions and manage staged changes with approval workflows in your Laravel application.
---

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
