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
 * Thrown when a staged change cannot be applied because it is not approved.
 *
 * This exception enforces the approval workflow requirement where staged changes
 * must be explicitly approved before they can be applied to tracked models. Prevents
 * unauthorized or unreviewed changes from being applied, maintaining data governance
 * and change control policies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeNotApprovedException extends CannotApplyStagedChangeException
{
    /**
     * Create a new exception for an unapproved staged change application attempt.
     *
     * @param StagedChange $stagedChange The staged change that is not approved
     */
    public static function forStagedChange(StagedChange $stagedChange): self
    {
        return new self(sprintf(
            'Staged change [%s] cannot be applied because its status is [%s]. Only approved changes can be applied.',
            $stagedChange->getKey(),
            $stagedChange->status->value,
        ));
    }
}
