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
 * Thrown when a staged change cannot be modified because it is not in a mutable state.
 *
 * Staged changes can only be modified while in the pending state. Once approved,
 * rejected, applied, or cancelled, they become immutable to preserve audit trails
 * and maintain data integrity. This exception prevents modifications to staged changes
 * that have progressed beyond the initial pending state.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeNotMutableException extends CannotModifyStagedChangeException
{
    /**
     * Create a new exception for an immutable staged change modification attempt.
     *
     * @param StagedChange $stagedChange The staged change that cannot be modified
     */
    public static function forStagedChange(StagedChange $stagedChange): self
    {
        return new self(sprintf(
            'Staged change [%s] cannot be modified because its status is [%s]. Only pending changes can be modified.',
            $stagedChange->getKey(),
            $stagedChange->status->value,
        ));
    }
}
