<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use RuntimeException;

/**
 * Base exception for revision not found errors.
 *
 * Serves as the parent exception for all revision lookup failures within the
 * Tracer system. Specific child exceptions provide detailed context about what
 * type of revision lookup failed (by ID, by model, etc.). Use this base type
 * for catch blocks that need to handle any revision not found scenario.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class RevisionNotFoundException extends RuntimeException implements TracerException
{
    // Abstract base - no factory methods
}
