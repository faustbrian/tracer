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
 * Base exception for errors that occur when attempting to apply a staged change.
 *
 * This abstract exception class serves as the parent for all exceptions related
 * to staged change application failures. Application failures can occur when a
 * staged change is not in the correct status, when the target model is invalid,
 * or when other constraints prevent the proposed changes from being committed.
 *
 * Concrete implementations extend this base to provide specific error contexts
 * for different application failure scenarios.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class CannotApplyStagedChangeException extends RuntimeException implements TracerException
{
    // Abstract base - no factory methods
}
