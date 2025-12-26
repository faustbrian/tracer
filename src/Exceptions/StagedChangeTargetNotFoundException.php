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
 * Thrown when a staged change cannot be applied because the target model was not found.
 *
 * This exception occurs during the application phase when the staged change references
 * a model that has been deleted or never existed. This typically happens in scenarios
 * where changes are approved after the target model has been removed from the database.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeTargetNotFoundException extends CannotApplyStagedChangeException
{
    /**
     * Create a new exception for a staged change with missing target model.
     *
     * @param  StagedChange $stagedChange The staged change that cannot be applied due to missing target model
     * @return self         The constructed exception with contextual error message
     */
    public static function forStagedChange(StagedChange $stagedChange): self
    {
        return new self(sprintf(
            'Staged change [%s] cannot be applied because the target model [%s:%s] was not found.',
            $stagedChange->getKey(),
            $stagedChange->stageable_type,
            $stagedChange->stageable_id,
        ));
    }
}
