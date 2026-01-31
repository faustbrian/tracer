<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Events;

use Cline\Tracer\Database\Models\Revision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a new revision is created for a traceable model.
 *
 * This event is fired whenever a change is recorded in the revision history,
 * providing access to both the newly created revision record and the model
 * that was tracked. Listeners can use this to trigger notifications, sync
 * external systems, or perform additional audit logging.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class RevisionCreated
{
    use Dispatchable;

    /**
     * Create a new revision created event instance.
     *
     * @param Revision $revision  The newly created revision record containing the tracked changes,
     *                            including the changed attributes, timestamps, and user context
     *                            for the modification that triggered this revision.
     * @param Model    $traceable The model instance that was modified and is being tracked for
     *                            revision history. This is the original model that triggered
     *                            the revision creation through the traceable trait.
     */
    public function __construct(
        public Revision $revision,
        public Model $traceable,
    ) {}
}
