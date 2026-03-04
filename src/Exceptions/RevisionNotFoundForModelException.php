<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Illuminate\Database\Eloquent\Model;

use function sprintf;

/**
 * Thrown when a revision cannot be found for a specific model.
 *
 * This exception occurs when attempting to retrieve a revision by version number
 * or revision ID for a particular Eloquent model instance. Used in revision
 * history navigation and rollback operations where the requested revision must
 * exist for the specific model being tracked.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RevisionNotFoundForModelException extends RevisionNotFoundException
{
    /**
     * Create a new exception for a missing model revision.
     *
     * @param Model      $model       The Eloquent model instance being queried
     * @param int|string $versionOrId The revision version number or ID that could not be found
     */
    public static function forModel(Model $model, int|string $versionOrId): self
    {
        return new self(sprintf(
            'Revision [%s] not found for model [%s:%s].',
            $versionOrId,
            $model->getMorphClass(),
            $model->getKey(),
        ));
    }
}
