<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Events;

use Cline\Tracer\Database\Models\StagedChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a staged change is rejected in the review workflow.
 *
 * This event is fired when a staged change is rejected during review, preventing
 * the proposed changes from being applied to the target model. The staged change
 * enters a terminal state and cannot be modified or resubmitted. A rejection
 * reason can be provided to document why the changes were not accepted.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StagedChangeRejected
{
    use Dispatchable;

    /**
     * Create a new staged change rejected event instance.
     *
     * @param StagedChange $stagedChange The staged change record that was rejected, containing
     *                                   the proposed changes and workflow metadata. The status
     *                                   will be 'rejected' at this point, representing a terminal
     *                                   state where no further modifications are possible.
     * @param null|Model   $rejector     The user or model that performed the rejection action.
     *                                   Null when the rejection was triggered automatically by
     *                                   system rules or validation failures without user context.
     * @param null|string  $reason       Optional explanation for why the staged change was rejected.
     *                                   This provides context for the rejection decision and can be
     *                                   used for audit trails or user feedback. Null when no reason
     *                                   was provided or the rejection was automatic.
     */
    public function __construct(
        public StagedChange $stagedChange,
        public ?Model $rejector,
        public ?string $reason,
    ) {}
}
