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
 * Base exception for errors that occur when attempting to modify a staged change.
 *
 * This abstract exception class serves as the parent for all exceptions related
 * to staged change modification failures. Modifications are only permitted when
 * a staged change is in pending status. Once a change is approved, rejected,
 * applied, or cancelled, it becomes immutable to preserve the audit trail.
 *
 * Concrete implementations extend this base to provide specific error contexts
 * for different modification failure scenarios, such as attempting to update
 * a change that has already been applied or trying to modify a rejected change.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class CannotModifyStagedChangeException extends RuntimeException implements TracerException
{
    // Abstract base - no factory methods
}
