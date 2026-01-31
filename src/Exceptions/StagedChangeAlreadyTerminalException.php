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
 * Thrown when a staged change cannot be cancelled because it is already in a terminal state.
 *
 * Terminal states (applied, cancelled, rejected) represent finalized staged changes
 * that can no longer be modified or cancelled. This exception prevents invalid state
 * transitions and ensures the staged change workflow maintains proper state integrity.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeAlreadyTerminalException extends CannotModifyStagedChangeException
{
    /**
     * Create a new exception for a staged change in terminal state.
     *
     * @param StagedChange $stagedChange The staged change instance in terminal state
     */
    public static function forStagedChange(StagedChange $stagedChange): self
    {
        return new self(sprintf(
            'Staged change [%s] cannot be cancelled because it is already in terminal state [%s].',
            $stagedChange->getKey(),
            $stagedChange->status->value,
        ));
    }
}
