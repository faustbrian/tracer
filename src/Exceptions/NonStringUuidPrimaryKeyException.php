<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use RuntimeException;

use function gettype;
use function sprintf;

/**
 * Thrown when a UUID primary key value is not a string.
 *
 * UUIDs (Universally Unique Identifiers) must be stored as string types in
 * Tracer. This exception prevents type mismatches that could cause revision
 * tracking failures or database integrity issues when handling UUID-based models.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NonStringUuidPrimaryKeyException extends RuntimeException implements TracerException
{
    /**
     * Create a new exception for an invalid UUID primary key type.
     *
     * @param mixed $value The invalid primary key value that was provided
     */
    public static function forValue(mixed $value): self
    {
        return new self(sprintf(
            'UUID primary key value must be a string, %s given.',
            gettype($value),
        ));
    }
}
