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
 * Thrown when a strategy class does not implement the required interface.
 *
 * This exception is raised during configuration validation when a registered
 * strategy class fails to implement the expected interface contract. Ensures
 * type safety in the strategy pattern implementation throughout the Tracer system.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidStrategyClassException extends InvalidConfigurationException
{
    /**
     * Create a new exception for an invalid strategy class.
     *
     * @param string $class     Fully qualified class name that failed validation
     * @param string $interface Fully qualified interface name that should be implemented
     */
    public static function forClass(string $class, string $interface): self
    {
        return new self(sprintf(
            'Strategy class [%s] must implement [%s].',
            $class,
            $interface,
        ));
    }
}
