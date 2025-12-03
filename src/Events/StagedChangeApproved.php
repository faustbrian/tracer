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
 * Event dispatched when a staged change is approved in the review workflow.
 *
 * This event is fired when a staged change transitions from pending to approved
 * status, indicating that the proposed changes have passed review and are ready
 * to be applied to the target model. The approval does not automatically apply
 * the changes - a separate application step is required.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StagedChangeApproved
{
    use Dispatchable;

    /**
     * Create a new staged change approved event instance.
     *
     * @param StagedChange $stagedChange The staged change record that was approved, containing
     *                                   the proposed changes and workflow metadata. The status
     *                                   will be 'approved' at this point, making the change
     *                                   eligible for application to the target model.
     * @param null|Model   $approver     The user or model that performed the approval action.
     *                                   Null when the approval was triggered automatically by
     *                                   system rules or when no authenticated user context exists.
     */
    public function __construct(
        public StagedChange $stagedChange,
        public ?Model $approver,
    ) {}
}
