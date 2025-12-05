<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Contracts;

use Cline\Tracer\Database\Models\StagedChange;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for models that support staged changes before persisting.
 *
 * Models implement this interface to enable a staging workflow where changes
 * must be approved before being applied. This supports multi-step approval
 * processes, audit requirements, and controlled modification workflows.
 *
 * Models implementing this interface only define relationship methods. All
 * configuration is managed via Tracer::configure() or config/tracer.php, and
 * business logic is accessed through the Tracer facade.
 *
 * ```php
 * class Article extends Model implements Stageable
 * {
 *     use HasStagedChanges;
 *
 *     // Relationships are auto-implemented by the trait
 * }
 *
 * // Propose changes for approval
 * $stagedChange = Tracer::stage($article, ['title' => 'New Title']);
 *
 * // Query pending changes
 * $pending = $article->pendingStagedChanges;
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Stageable
{
    /**
     * Get all staged changes for this model.
     *
     * Returns all staged changes regardless of status, including pending,
     * approved, rejected, and applied changes. Use filtered relationships
     * for specific workflow states.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function stagedChanges(): MorphMany;

    /**
     * Get pending staged changes awaiting approval.
     *
     * Returns only changes with pending status that have not yet been
     * approved, rejected, or applied. These changes are awaiting review
     * in the approval workflow.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function pendingStagedChanges(): MorphMany;

    /**
     * Get approved staged changes ready to apply.
     *
     * Returns changes that have passed the approval workflow and are
     * ready to be applied to the model. These changes have not yet been
     * applied but have received all required approvals.
     *
     * @return MorphMany<StagedChange, $this>
     */
    public function approvedStagedChanges(): MorphMany;
}
