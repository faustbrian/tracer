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
use Illuminate\Support\Facades\Config;

use function array_merge;
use function event;
use function max;
use function now;

/**
 * Quorum-based approval strategy requiring multiple approvals.
 *
 * Implements a voting system where staged changes require a configurable number of
 * approvals before they can be applied. Both approval and rejection thresholds are
 * supported, allowing for democratic decision-making in change management workflows.
 *
 * The strategy prevents duplicate votes from the same user and tracks voting progress
 * in the staged change metadata. Configuration values can be set globally via the
 * tracer.quorum configuration or per-change via approval_metadata.
 *
 * ```php
 * // Configure globally in config/tracer.php
 * 'quorum' => [
 *     'approvals_required' => 2,
 *     'rejections_required' => 1,
 * ],
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class QuorumApprovalStrategy implements ApprovalStrategy
{
    /**
     * Create a new quorum approval strategy instance.
     *
     * @param TracerManager $manager The tracer manager instance for event dispatching and configuration access
     */
    public function __construct(
        private TracerManager $manager,
    ) {}

    /**
     * Determine if the staged change can be approved by the given approver.
     *
     * Checks if the staged change is in pending status and whether the approver
     * has already cast a vote (approval or rejection). Returns false if the change
     * is not pending or if the approver has already voted.
     *
     * @param  StagedChange $stagedChange The staged change to check approval eligibility for
     * @param  null|Model   $approver     The model attempting to approve, or null for anonymous approval
     * @return bool         True if the change can be approved, false otherwise
     */
    public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool
    {
        // Can only approve pending changes
        if ($stagedChange->status !== StagedChangeStatus::Pending) {
            return false;
        }

        // Check if this approver has already voted
        return !($approver instanceof Model && $this->hasAlreadyVoted($stagedChange, $approver));
    }

    /**
     * Determine if the staged change can be rejected by the given rejector.
     *
     * Checks if the staged change is in pending status and whether the rejector
     * has already cast a vote (approval or rejection). Returns false if the change
     * is not pending or if the rejector has already voted.
     *
     * @param  StagedChange $stagedChange The staged change to check rejection eligibility for
     * @param  null|Model   $rejector     The model attempting to reject, or null for anonymous rejection
     * @return bool         True if the change can be rejected, false otherwise
     */
    public function canReject(StagedChange $stagedChange, ?Model $rejector = null): bool
    {
        // Can only reject pending changes
        if ($stagedChange->status !== StagedChangeStatus::Pending) {
            return false;
        }

        // Check if this rejector has already voted
        return !($rejector instanceof Model && $this->hasAlreadyVoted($stagedChange, $rejector));
    }

    /**
     * Record an approval vote and determine if quorum has been reached.
     *
     * Creates an approval record and checks if the required number of approvals
     * has been met. If quorum is reached, the staged change status is updated to
     * Approved and an event is dispatched. Otherwise, the approval is recorded
     * and the metadata is updated with current progress.
     *
     * @param  StagedChange $stagedChange The staged change to approve
     * @param  null|Model   $approver     The model approving the change, or null for anonymous approval
     * @param  null|string  $comment      Optional comment explaining the approval decision
     * @return bool         True if quorum was reached and change is now approved, false if more approvals needed
     */
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool
    {
        if (!$this->canApprove($stagedChange, $approver)) {
            return false;
        }

        $sequence = $stagedChange->approvals()->count() + 1;

        // Record the approval
        $stagedChange->approvals()->create([
            'approved' => true,
            'comment' => $comment,
            'approver_type' => $approver?->getMorphClass(),
            'approver_id' => $approver?->getKey(),
            'sequence' => $sequence,
        ]);

        // Check if quorum is reached
        $approvalsRequired = $this->getRequiredApprovals($stagedChange);
        $approvalsReceived = $stagedChange->approvals()->where('approved', true)->count();

        if ($approvalsReceived >= $approvalsRequired) {
            $stagedChange->status = StagedChangeStatus::Approved;
            $stagedChange->approval_metadata = [
                'quorum_reached' => true,
                'approvals_required' => $approvalsRequired,
                'approvals_received' => $approvalsReceived,
                'approved_at' => now()->toIso8601String(),
            ];
            $stagedChange->save();

            $this->dispatchApprovedEvent($stagedChange, $approver);

            return true;
        }

        // Update metadata with current progress
        $stagedChange->approval_metadata = [
            'quorum_reached' => false,
            'approvals_required' => $approvalsRequired,
            'approvals_received' => $approvalsReceived,
        ];
        $stagedChange->save();

        return false;
    }

    /**
     * Record a rejection vote and determine if rejection threshold has been reached.
     *
     * Creates a rejection record and checks if the required number of rejections
     * has been met. If threshold is reached, the staged change status is updated to
     * Rejected and an event is dispatched. Otherwise, the rejection is recorded
     * and the metadata is updated with current progress.
     *
     * @param  StagedChange $stagedChange The staged change to reject
     * @param  null|Model   $rejector     The model rejecting the change, or null for anonymous rejection
     * @param  null|string  $reason       Optional reason explaining the rejection decision
     * @return bool         True if rejection threshold was reached and change is now rejected, false if more rejections needed
     */
    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool
    {
        if (!$this->canReject($stagedChange, $rejector)) {
            return false;
        }

        $sequence = $stagedChange->approvals()->count() + 1;

        // Record the rejection
        $stagedChange->approvals()->create([
            'approved' => false,
            'comment' => $reason,
            'approver_type' => $rejector?->getMorphClass(),
            'approver_id' => $rejector?->getKey(),
            'sequence' => $sequence,
        ]);

        // Check if rejection threshold is reached
        $rejectionsRequired = $this->getRequiredRejections($stagedChange);
        $rejectionsReceived = $stagedChange->approvals()->where('approved', false)->count();

        if ($rejectionsReceived >= $rejectionsRequired) {
            $stagedChange->status = StagedChangeStatus::Rejected;
            $stagedChange->rejection_reason = $reason;
            $stagedChange->approval_metadata = [
                'rejection_threshold_reached' => true,
                'rejections_required' => $rejectionsRequired,
                'rejections_received' => $rejectionsReceived,
                'rejected_at' => now()->toIso8601String(),
            ];
            $stagedChange->save();

            $this->dispatchRejectedEvent($stagedChange, $rejector, $reason);

            return true;
        }

        // Update metadata with current progress
        $currentMetadata = $stagedChange->approval_metadata ?? [];
        $stagedChange->approval_metadata = array_merge($currentMetadata, [
            'rejections_received' => $rejectionsReceived,
        ]);
        $stagedChange->save();

        return false;
    }

    /**
     * Get the current approval status and voting progress metadata.
     *
     * Returns comprehensive status information including the number of approvals
     * and rejections required and received, remaining approvals needed, and overall
     * approval state. Useful for displaying voting progress to users.
     *
     * @param  StagedChange         $stagedChange The staged change to get status for
     * @return array<string, mixed> Status array containing voting progress and approval state
     */
    public function status(StagedChange $stagedChange): array
    {
        $approvalsRequired = $this->getRequiredApprovals($stagedChange);
        $rejectionsRequired = $this->getRequiredRejections($stagedChange);
        $approvalsReceived = $stagedChange->approvals()->where('approved', true)->count();
        $rejectionsReceived = $stagedChange->approvals()->where('approved', false)->count();

        return [
            'strategy' => $this->identifier(),
            'status' => $stagedChange->status->value,
            'approvals_required' => $approvalsRequired,
            'approvals_received' => $approvalsReceived,
            'rejections_required' => $rejectionsRequired,
            'rejections_received' => $rejectionsReceived,
            'remaining_approvals' => max(0, $approvalsRequired - $approvalsReceived),
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
        return 'quorum';
    }

    /**
     * Check if a voter has already cast a vote on this staged change.
     *
     * Prevents duplicate voting by checking if the voter has already submitted
     * an approval or rejection for this staged change. Used to enforce one vote
     * per user regardless of whether it was an approval or rejection.
     *
     * @param  StagedChange $stagedChange The staged change to check for existing votes
     * @param  Model        $voter        The model to check for existing votes
     * @return bool         True if the voter has already voted, false otherwise
     */
    private function hasAlreadyVoted(StagedChange $stagedChange, Model $voter): bool
    {
        return $stagedChange->approvals()
            ->where('approver_type', $voter->getMorphClass())
            ->where('approver_id', $voter->getKey())
            ->exists();
    }

    /**
     * Get the number of approvals required for this staged change.
     *
     * Checks the staged change metadata first for per-change requirements,
     * then falls back to the global configuration value. Defaults to 2 if
     * not configured anywhere.
     *
     * @param  StagedChange $stagedChange The staged change to get approval requirements for
     * @return int          The number of approvals required to approve the change
     */
    private function getRequiredApprovals(StagedChange $stagedChange): int
    {
        // Check if specified in metadata
        $metadata = $stagedChange->approval_metadata ?? [];

        if (isset($metadata['approvals_required'])) {
            return (int) $metadata['approvals_required'];
        }

        // Fall back to configuration
        /** @var int */
        return Config::get('tracer.quorum.approvals_required', 2);
    }

    /**
     * Get the number of rejections required to reject this staged change.
     *
     * Checks the staged change metadata first for per-change requirements,
     * then falls back to the global configuration value. Defaults to 1 if
     * not configured anywhere, allowing a single rejection to block the change.
     *
     * @param  StagedChange $stagedChange The staged change to get rejection requirements for
     * @return int          The number of rejections required to reject the change
     */
    private function getRequiredRejections(StagedChange $stagedChange): int
    {
        // Check if specified in metadata
        $metadata = $stagedChange->approval_metadata ?? [];

        if (isset($metadata['rejections_required'])) {
            return (int) $metadata['rejections_required'];
        }

        // Fall back to configuration
        /** @var int */
        return Config::get('tracer.quorum.rejections_required', 1);
    }

    /**
     * Dispatch the approved event if events are enabled in the manager.
     *
     * @param StagedChange $stagedChange The staged change that was approved
     * @param null|Model   $approver     The model that cast the final approval, or null for anonymous
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
     * @param null|Model   $rejector     The model that cast the final rejection, or null for anonymous
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
