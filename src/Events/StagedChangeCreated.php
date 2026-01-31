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
 * Event dispatched when a new staged change is created.
 *
 * This event is fired when proposed changes are staged for a model, creating
 * a new staged change record in pending status. The changes are not yet applied
 * to the target model and require approval through the review workflow before
 * they can be committed.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class StagedChangeCreated
{
    use Dispatchable;

    /**
     * Create a new staged change created event instance.
     *
     * @param StagedChange $stagedChange The newly created staged change record containing the
     *                                   proposed modifications. The change will be in pending
     *                                   status and can be modified until it enters the review
     *                                   workflow through approval or rejection.
     * @param Model        $stageable    The target model that will receive the changes once they
     *                                   are approved and applied. This model remains unchanged
     *                                   until the staged change is successfully applied.
     */
    public function __construct(
        public StagedChange $stagedChange,
        public Model $stageable,
    ) {}
}
