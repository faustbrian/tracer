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
 * Thrown when a diff strategy identifier is not registered in the configuration.
 *
 * This exception indicates a configuration error where a model or revision references
 * a diff strategy that hasn't been registered in the tracer.diff_strategies configuration
 * array. The identifier must be registered before it can be used for calculating changes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnknownDiffStrategyException extends InvalidConfigurationException
{
    /**
     * Create a new exception for an unregistered diff strategy identifier.
     *
     * @param  string $identifier The diff strategy identifier that was not found in the configuration
     * @return self   The constructed exception with instructions for resolving the configuration issue
     */
    public static function forIdentifier(string $identifier): self
    {
        return new self(sprintf(
            'Unknown diff strategy identifier: [%s]. Register it in the tracer.diff_strategies configuration.',
            $identifier,
        ));
    }
}
