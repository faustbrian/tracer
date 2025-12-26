<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Enums;

/**
 * Represents the lifecycle status of a staged change in the revision workflow.
 *
 * Staged changes progress through various states from creation through approval
 * or rejection, ultimately reaching a terminal state when applied or cancelled.
 * This enum enforces workflow constraints and determines which operations are
 * permitted at each stage.
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum StagedChangeStatus: string
{
    /**
     * Initial state - the staged change is awaiting review and can be modified.
     */
    case Pending = 'pending';

    /**
     * Approved state - the staged change has passed review and is ready to be applied to the target model.
     */
    case Approved = 'approved';

    /**
     * Rejected state - the staged change failed review and will not be applied.
     */
    case Rejected = 'rejected';

    /**
     * Terminal state - the staged change has been successfully applied to the target model.
     */
    case Applied = 'applied';

    /**
     * Terminal state - the staged change was cancelled before reaching review.
     */
    case Cancelled = 'cancelled';

    /**
     * Determine if the staged change can still be modified.
     *
     * Only pending changes are mutable. Once a change moves to approved, rejected,
     * applied, or cancelled status, it becomes immutable to preserve audit trail.
     *
     * @return bool True if the change is in pending status and can be modified
     */
    public function isMutable(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Determine if the staged change is ready to be applied.
     *
     * Changes can only be applied when they have been approved through the review
     * workflow. Pending changes must be approved first, while rejected, applied,
     * or cancelled changes cannot be applied.
     *
     * @return bool True if the change has been approved and can be applied
     */
    public function canBeApplied(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Determine if the staged change is in a terminal state.
     *
     * Terminal states represent the end of the staged change lifecycle. Once in a
     * terminal state, no further workflow transitions are possible. This includes
     * changes that have been applied, rejected, or cancelled.
     *
     * @return bool True if the change is in applied, rejected, or cancelled status
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Applied, self::Rejected, self::Cancelled => true,
            default => false,
        };
    }
}
