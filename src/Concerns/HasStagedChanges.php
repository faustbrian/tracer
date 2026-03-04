<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Concerns;

use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Enables staged changes for Eloquent models.
 *
 * This trait provides the relationship methods needed for staged changes.
 * Configuration is managed via Tracer::configure() or config/tracer.php.
 * Business logic is accessed via the Tracer facade.
 *
 * @mixin Model
 * @mixin Stageable
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasStagedChanges
{
    /**
     * Get all staged changes for this model.
     *
     * Returns all staged changes ordered by creation date, most recent first.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function stagedChanges(): MorphMany
    {
        return $this->morphMany(StagedChange::class, 'stageable')->orderByDesc('created_at');
    }

    /**
     * Get pending staged changes awaiting approval.
     *
     * Filters staged changes to only those with Pending status that have not
     * yet been approved or rejected.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function pendingStagedChanges(): MorphMany
    {
        return $this->stagedChanges()->where('status', StagedChangeStatus::Pending);
    }

    /**
     * Get approved staged changes ready to be applied.
     *
     * Filters staged changes to only those with Approved status that are
     * ready to be applied to the model.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function approvedStagedChanges(): MorphMany
    {
        return $this->stagedChanges()->where('status', StagedChangeStatus::Approved);
    }
}
