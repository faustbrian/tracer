<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use function sprintf;

/**
 * Thrown when a revision cannot be found by its ID.
 *
 * This exception occurs during direct revision lookup operations when attempting
 * to retrieve a specific revision record using its primary key identifier. Used
 * in scenarios where revision existence is expected and failure indicates a data
 * integrity issue or invalid reference.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RevisionNotFoundByIdException extends RevisionNotFoundException
{
    /**
     * Create a new exception for a missing revision by ID.
     *
     * @param int|string $id The revision identifier that could not be found
     */
    public static function forId(int|string $id): self
    {
        return new self(sprintf(
            'Revision with ID [%s] not found.',
            $id,
        ));
    }
}
