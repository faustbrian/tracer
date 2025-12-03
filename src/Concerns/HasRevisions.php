<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Concerns;

use Cline\Tracer\Contracts\Traceable;
use Cline\Tracer\Database\Models\Revision;
use Cline\Tracer\Observers\TraceableObserver;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Enables revision tracking for Eloquent models.
 *
 * This trait provides the relationship methods needed for revision tracking.
 * Configuration is managed via Tracer::configure() or config/tracer.php.
 * Business logic is accessed via the Tracer facade.
 *
 * @mixin Model
 * @mixin Traceable
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasRevisions
{
    /**
     * Boot the trait and register the observer.
     *
     * Automatically attaches the TraceableObserver to listen for model events
     * and record revisions. Called by Laravel during model initialization.
     */
    #[Boot()]
    public static function registerTraceableObserver(): void
    {
        static::observe(TraceableObserver::class);
    }

    /**
     * Get all revisions for this model.
     *
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'traceable')->orderByDesc('version');
    }

    /**
     * Get the latest revision for this model.
     *
     * Returns the most recent revision based on version number ordering.
     *
     * @return null|Revision The latest revision, or null if no revisions exist
     */
    public function latestRevision(): ?Revision
    {
        return $this->revisions()->first();
    }

    /**
     * Get a specific revision by version number.
     *
     * Version numbers are sequential integers starting from 1 for the first
     * revision and incrementing for each subsequent change.
     *
     * @param  int           $version The version number to retrieve
     * @return null|Revision The matching revision, or null if not found
     */
    public function getRevision(int $version): ?Revision
    {
        return $this->revisions()->where('version', $version)->first();
    }
}
