<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Strategies\Approval;

use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Events\StagedChangeApproved;
use Cline\Tracer\Events\StagedChangeRejected;
use Cline\Tracer\TracerManager;
use Illuminate\Database\Eloquent\Model;

use function event;
use function now;

/**
 * Simple single-approver approval strategy.
 *
 * Implements a straightforward approval workflow where a single approval is sufficient
 * to mark a staged change as ready to apply. This strategy is ideal for small teams,
 * automated systems, or scenarios where formal multi-party approval is not required.
 *
 * Unlike the quorum strategy, there is no voting mechanism - the first approval or
 * rejection immediately finalizes the decision. Authorization should be handled via
 * Laravel policies or gates to control who can approve changes.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class SimpleApprovalStrategy implements ApprovalStrategy
{
    /**
     * Create a new simple approval strategy instance.
     *
     * @param TracerManager $manager The tracer manager instance for event dispatching
     */
    public function __construct(
        private TracerManager $manager,
    ) {}

    /**
     * Determine if the staged change can be approved by the given approver.
     *
     * Returns true only if the staged change is in pending status. Authorization
     * checks should be performed separately via Laravel policies or gates before
     * calling the approve method.
     *
     * @param  StagedChange $stagedChange The staged change to check approval eligibility for
     * @param  null|Model   $approver     The model attempting to approve (unused in simple strategy)
     * @return bool         True if the change is pending and can be approved
     */
    public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool
    {
        // Can only approve pending changes
        // By default, anyone can approve (customize via authorization policies)
        return $stagedChange->status === StagedChangeStatus::Pending;
    }

    /**
     * Determine if the staged change can be rejected by the given rejector.
     *
     * Returns true only if the staged change is in pending status. Authorization
     * checks should be performed separately via Laravel policies or gates before
     * calling the reject method.
     *
     * @param  StagedChange $stagedChange The staged change to check rejection eligibility for
     * @param  null|Model   $rejector     The model attempting to reject (unused in simple strategy)
     * @return bool         True if the change is pending and can be rejected
     */
    public function canReject(StagedChange $stagedChange, ?Model $rejector = null): bool
    {
        // Can only reject pending changes
        // By default, anyone can reject (customize via authorization policies)
        return $stagedChange->status === StagedChangeStatus::Pending;
    }

    /**
     * Record an approval and immediately mark the change as approved.
     *
     * Creates an approval record and updates the staged change status to Approved
     * in a single operation. Dispatches the StagedChangeApproved event if events
     * are enabled. Always returns true if the change can be approved.
     *
     * @param  StagedChange $stagedChange The staged change to approve
     * @param  null|Model   $approver     The model approving the change, or null for anonymous approval
     * @param  null|string  $comment      Optional comment explaining the approval decision
     * @return bool         True if the change was approved, false if it cannot be approved
     */
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        if (!$this->canApprove($stagedChange, $approver)) {
            return false;
        }

        // Record the approval
        $stagedChange->approvals()->create([
            'approved' => true,
            'comment' => $comment,
            'approver_type' => $approver?->getMorphClass(),
            'approver_id' => $approver?->getKey(),
            'sequence' => 1,
        ]);

        // Simple strategy: one approval is enough
        $stagedChange->status = StagedChangeStatus::Approved;
        $stagedChange->approval_metadata = [
            'approved_by_type' => $approver?->getMorphClass(),
            'approved_by_id' => $approver?->getKey(),
            'approved_at' => now()->toIso8601String(),
        ];
        $stagedChange->save();

        $this->dispatchApprovedEvent($stagedChange, $approver);

        return true;
    }

    /**
     * Record a rejection and immediately mark the change as rejected.
     *
     * Creates a rejection record and updates the staged change status to Rejected
     * in a single operation. Dispatches the StagedChangeRejected event if events
     * are enabled. Always returns true if the change can be rejected.
     *
     * @param  StagedChange $stagedChange The staged change to reject
     * @param  null|Model   $rejector     The model rejecting the change, or null for anonymous rejection
     * @param  null|string  $reason       Optional reason explaining the rejection decision
     * @return bool         True if the change was rejected, false if it cannot be rejected
     */
    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool
    {
        if (!$this->canReject($stagedChange, $rejector)) {
            return false;
        }

        // Record the rejection
        $stagedChange->approvals()->create([
            'approved' => false,
            'comment' => $reason,
            'approver_type' => $rejector?->getMorphClass(),
            'approver_id' => $rejector?->getKey(),
            'sequence' => 1,
        ]);

        $stagedChange->status = StagedChangeStatus::Rejected;
        $stagedChange->rejection_reason = $reason;
        $stagedChange->approval_metadata = [
            'rejected_by_type' => $rejector?->getMorphClass(),
            'rejected_by_id' => $rejector?->getKey(),
            'rejected_at' => now()->toIso8601String(),
        ];
        $stagedChange->save();

        $this->dispatchRejectedEvent($stagedChange, $rejector, $reason);

        return true;
    }

    /**
     * Get the current approval status metadata.
     *
     * Returns status information indicating approval requirements and current state.
     * For the simple strategy, only one approval is ever required.
     *
     * @param  StagedChange         $stagedChange The staged change to get status for
     * @return array<string, mixed> Status array containing approval state information
     */
    public function status(StagedChange $stagedChange): array
    {
        return [
            'strategy' => $this->identifier(),
            'status' => $stagedChange->status->value,
            'approvals_required' => 1,
            'approvals_received' => $stagedChange->approvals()->where('approved', true)->count(),
            'is_approved' => $stagedChange->status === StagedChangeStatus::Approved,
            'is_rejected' => $stagedChange->status === StagedChangeStatus::Rejected,
            'can_be_approved' => $stagedChange->status === StagedChangeStatus::Pending,
        ];
    }

    /**
     * Get the unique identifier for this strategy.
     *
     * @return string The strategy identifier used in configuration
     */
    public function identifier(): string
    {
        return 'simple';
    }

    /**
     * Dispatch the approved event if events are enabled in the manager.
     *
     * @param StagedChange $stagedChange The staged change that was approved
     * @param null|Model   $approver     The model that approved the change, or null for anonymous
     */
    private function dispatchApprovedEvent(StagedChange $stagedChange, ?Model $approver): void
    {
        if (!$this->manager->eventsEnabled()) {
            return;
        }

        event(
            new StagedChangeApproved($stagedChange, $approver),
        );
    }

    /**
     * Dispatch the rejected event if events are enabled in the manager.
     *
     * @param StagedChange $stagedChange The staged change that was rejected
     * @param null|Model   $rejector     The model that rejected the change, or null for anonymous
     * @param null|string  $reason       The reason for rejection
     */
    private function dispatchRejectedEvent(StagedChange $stagedChange, ?Model $rejector, ?string $reason): void
    {
        if (!$this->manager->eventsEnabled()) {
            return;
        }

        event(
            new StagedChangeRejected($stagedChange, $rejector, $reason),
        );
    }
}
