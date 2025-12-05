<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Contracts;

use Cline\Tracer\Database\Models\Revision;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Contract for models that support automatic revision tracking.
 *
 * Models implement this interface to enable full audit trails and version
 * history. Every create, update, delete, and restore operation automatically
 * creates a revision record capturing the state changes with metadata about
 * who made the change and when.
 *
 * Models implementing this interface only define relationship methods. All
 * configuration is managed via Tracer::configure() or config/tracer.php, and
 * business logic is accessed through the Tracer facade.
 *
 * ```php
 * class Article extends Model implements Traceable
 * {
 *     use HasRevisions;
 *
 *     // Revisions are automatically created on model events
 * }
 *
 * // Query revision history
 * $history = $article->revisions()->orderBy('version')->get();
 *
 * // Revert to a previous state
 * Tracer::revert($article, 5); // Revert to version 5
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Traceable
{
    /**
     * Get all revisions for this model.
     *
     * Returns all historical snapshots ordered by creation date, including
     * revisions for creates, updates, deletes, restores, and reverts. Each
     * revision captures the complete state transition with old and new values.
     *
     * @return MorphMany<Revision, $this>
     */
    public function revisions(): MorphMany;

    /**
     * Get the latest revision for this model.
     *
     * Returns the most recent revision record, typically representing the
     * current state or most recent change. Returns null if no revisions exist.
     */
    public function latestRevision(): ?Revision;

    /**
     * Get a specific revision by version number.
     *
     * Retrieves a historical snapshot at a specific version. Version numbers
     * are sequential integers starting from 1, incrementing with each change.
     * Returns null if the version does not exist.
     *
     * @param int $version The sequential version number to retrieve
     */
    public function getRevision(int $version): ?Revision;
}
