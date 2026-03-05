<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Facade\IgnitionContracts\BaseSolution;
use Facade\IgnitionContracts\ProvidesSolution;
use Facade\IgnitionContracts\Solution;
use RuntimeException;

/**
 * Base exception for errors related to invalid Tracer package configuration.
 *
 * This abstract exception class serves as the parent for all exceptions thrown
 * when the Tracer package detects invalid, conflicting, or missing configuration
 * values. Configuration errors are typically detected during package initialization
 * or service provider boot, before any runtime operations occur.
 *
 * Concrete implementations extend this base to provide specific error contexts
 * for different configuration validation failures, such as conflicting morph key
 * mapping strategies, missing required configuration values, or invalid setting
 * combinations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidConfigurationException extends RuntimeException implements ProvidesSolution, TracerException
{
    // Abstract base - no factory methods

    public function getSolution(): Solution
    {
        /** @var BaseSolution $solution */
        $solution = BaseSolution::create('Review package usage and configuration.');

        return $solution
            ->setSolutionDescription('Exception: '.$this->getMessage())
            ->setDocumentationLinks([
                'Package documentation' => 'https://github.com/cline/tracer',
            ]);
    }
}
