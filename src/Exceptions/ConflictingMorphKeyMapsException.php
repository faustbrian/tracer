<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

/**
 * Exception thrown when both morphKeyMap and enforceMorphKeyMap are configured simultaneously.
 *
 * The Tracer package supports two mutually exclusive approaches for mapping morph
 * types to model classes: the permissive morphKeyMap and the strict enforceMorphKeyMap.
 * Configuring both creates an ambiguous state where it's unclear which mapping
 * strategy should be used. This exception enforces the constraint that only one
 * approach can be configured at a time.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ConflictingMorphKeyMapsException extends InvalidConfigurationException
{
    /**
     * Create a new conflicting morph key maps exception instance.
     *
     * @return self The exception instance with a descriptive message explaining
     *              that only one morph key mapping strategy can be configured
     */
    public static function create(): self
    {
        return new self('Cannot configure both morphKeyMap and enforceMorphKeyMap. Use one or the other.');
    }
}
