<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Cline\Tracer\Database\Models\StagedChange;

use function sprintf;

/**
 * Thrown when a staged change apply operation fails.
 *
 * This exception captures failures during the staged change application process,
 * such as validation errors, database constraint violations, or strategy execution
 * failures. The reason parameter provides diagnostic information to help identify
 * and resolve the underlying cause of the failure.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeApplyFailedException extends CannotApplyStagedChangeException
{
    /**
     * Create a new exception for a failed staged change application.
     *
     * @param StagedChange $stagedChange The staged change that failed to apply
     * @param string       $reason       Detailed explanation of why the application failed
     */
    public static function forStagedChange(StagedChange $stagedChange, string $reason): self
    {
        return new self(sprintf(
            'Staged change [%s] failed to apply: %s',
            $stagedChange->getKey(),
            $reason,
        ));
    }
}
