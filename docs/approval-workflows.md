---
title: Approval Workflows
description: Learn how to configure approval strategies for staged changes, including built-in strategies and creating custom workflows.
---

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
