<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Throwable;

/**
 * Marker interface for all Tracer package exceptions.
 *
 * Provides a unified exception hierarchy allowing consumers to catch all package-specific
 * exceptions with a single catch block. All exceptions thrown by the Tracer package implement
 * this interface, enabling targeted error handling while preserving exception-specific details.
 *
 * ```php
 * try {
 *     $tracer->apply($stagedChange);
 * } catch (TracerException $e) {
 *     // Handle any Tracer-related exception
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface TracerException extends Throwable
{
    // Marker interface - no methods required
}
