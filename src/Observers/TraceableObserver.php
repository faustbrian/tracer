<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Observers;

use Cline\Tracer\Contracts\Traceable;
use Cline\Tracer\Tracer;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer for automatic revision tracking on Traceable models.
 *
 * Automatically registers with Eloquent models using the Traceable trait to capture
 * all lifecycle events (created, updated, deleted, restored). Each event triggers
 * revision recording through the RevisionConductor, which handles diff calculation,
 * causer resolution, and storage based on the configured strategies.
 *
 * This observer respects the tracking state - if tracking is disabled via
 * withoutTracking(), events are silently ignored to prevent recursive tracking loops.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TraceableObserver
{
    /**
     * Handle the model "created" event to record creation revisions.
     *
     * Records a creation revision capturing the initial state of the model.
     * Only processes the event if tracking is currently enabled for the model.
     *
     * @param Model&Traceable $model The model instance that was created
     */
    public function created(Model $model): void
    {
        $conductor = Tracer::revisions($model);

        if (!$conductor->isTrackingEnabled()) {
            return;
        }

        $conductor->recordCreated();
    }

    /**
     * Handle the model "updated" event to record update revisions.
     *
     * Records a revision capturing the changes between the model's previous and current
     * state using the configured diff strategy. Only processes the event if tracking
     * is currently enabled for the model.
     *
     * @param Model&Traceable $model The model instance that was updated
     */
    public function updated(Model $model): void
    {
        $conductor = Tracer::revisions($model);

        if (!$conductor->isTrackingEnabled()) {
            return;
        }

        $conductor->recordUpdated();
    }

    /**
     * Handle the model "deleted" event to record deletion revisions.
     *
     * Records a deletion revision capturing the final state of the model before removal.
     * For soft-deleted models, this captures the state before soft deletion. Only processes
     * the event if tracking is currently enabled for the model.
     *
     * @param Model&Traceable $model The model instance that was deleted
     */
    public function deleted(Model $model): void
    {
        $conductor = Tracer::revisions($model);

        if (!$conductor->isTrackingEnabled()) {
            return;
        }

        $conductor->recordDeleted();
    }

    /**
     * Handle the model "restored" event to record restoration revisions.
     *
     * Records a restoration revision when a soft-deleted model is brought back from
     * deletion. Only processes the event if tracking is currently enabled for the model.
     *
     * @param Model&Traceable $model The model instance that was restored from soft deletion
     */
    public function restored(Model $model): void
    {
        $conductor = Tracer::revisions($model);

        if (!$conductor->isTrackingEnabled()) {
            return;
        }

        $conductor->recordRestored();
    }
}
