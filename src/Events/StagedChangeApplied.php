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
 * Event dispatched when a staged change is successfully applied to the target model.
 *
 * This event is fired after the staged change has been applied and the target
 * model has been updated with the proposed changes. The staged change status
 * is set to 'applied' at this point. Listeners can use this to trigger
 * notifications, sync related systems, or perform post-application cleanup.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StagedChangeApplied
{
    use Dispatchable;

    /**
     * Create a new staged change applied event instance.
     *
     * @param StagedChange $stagedChange The staged change record that was applied, containing
     *                                   the proposed changes and workflow metadata. At this point
     *                                   the status will be 'applied' and the changes have been
     *                                   committed to the target model.
     * @param Model        $stageable    The model instance that had the staged changes applied to it.
     *                                   This is the target model that received the updates from
     *                                   the staged change record.
     * @param null|Model   $appliedBy    The user or model that performed the application operation.
     *                                   Null when the application was triggered automatically or by
     *                                   system processes rather than an authenticated user.
     */
    public function __construct(
        public StagedChange $stagedChange,
        public Model $stageable,
        public ?Model $appliedBy,
    ) {}
}
