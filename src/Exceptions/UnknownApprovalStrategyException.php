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
 * Thrown when an approval strategy identifier is not registered in the configuration.
 *
 * This exception indicates a configuration error where a staged change references
 * an approval strategy that hasn't been registered in the tracer.approval_strategies
 * configuration array. The identifier must be registered before it can be used.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnknownApprovalStrategyException extends InvalidConfigurationException
{
    /**
     * Create a new exception for an unregistered approval strategy identifier.
     *
     * @param  string $identifier The approval strategy identifier that was not found in the configuration
     * @return self   The constructed exception with instructions for resolving the configuration issue
     */
    public static function forIdentifier(string $identifier): self
    {
        return new self(sprintf(
            'Unknown approval strategy identifier: [%s]. Register it in the tracer.approval_strategies configuration.',
            $identifier,
        ));
    }
}
