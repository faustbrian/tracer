<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Contracts;

use Cline\Tracer\Database\Models\StagedChange;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for strategies that determine when a staged change can be applied.
 *
 * Implementations define approval workflows, from simple single-approver patterns
 * to complex multi-step, quorum-based, or rule-engine-driven approval flows.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ApprovalStrategy
{
    /**
     * Determine if the staged change can be approved by the given approver.
     *
     * @param  StagedChange $stagedChange The staged change being evaluated
     * @param  null|Model   $approver     The user/entity attempting to approve (null for system)
     * @return bool         Whether the approver can approve this change
     */
    public function canApprove(StagedChange $stagedChange, ?Model $approver = null): bool;

    /**
     * Determine if the staged change can be rejected by the given rejector.
     *
     * @param  StagedChange $stagedChange The staged change being evaluated
     * @param  null|Model   $rejector     The user/entity attempting to reject (null for system)
     * @return bool         Whether the rejector can reject this change
     */
    public function canReject(StagedChange $stagedChange, ?Model $rejector = null): bool;

    /**
     * Record an approval and determine if the change is now fully approved.
     *
     * @param  StagedChange $stagedChange The staged change being approved
     * @param  null|Model   $approver     The user/entity approving (null for system)
     * @param  null|string  $comment      Optional comment for the approval
     * @return bool         Whether the change is now fully approved and ready to apply
     */
    public function approve(StagedChange $stagedChange, ?Model $approver = null, ?string $comment = null): bool;

    /**
     * Record a rejection.
     *
     * @param  StagedChange $stagedChange The staged change being rejected
     * @param  null|Model   $rejector     The user/entity rejecting (null for system)
     * @param  null|string  $reason       Optional reason for the rejection
     * @return bool         Whether the rejection was recorded successfully
     */
    public function reject(StagedChange $stagedChange, ?Model $rejector = null, ?string $reason = null): bool;

    /**
     * Get the current approval status metadata.
     *
     * @param  StagedChange         $stagedChange The staged change to check
     * @return array<string, mixed> Status information (e.g., approvals_needed, current_approvers, etc.)
     */
    public function status(StagedChange $stagedChange): array;

    /**
     * Get the unique identifier for this strategy.
     *
     * Used to store which strategy governs a given staged change,
     * allowing the system to use the correct strategy when processing.
     */
    public function identifier(): string;
}
