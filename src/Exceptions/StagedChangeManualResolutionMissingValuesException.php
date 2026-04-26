<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Cline\Tracer\Database\Models\StagedChange;

use function implode;
use function sprintf;
use function var_export;

/**
 * Thrown when manual conflict resolution does not provide all required values.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeManualResolutionMissingValuesException extends CannotApplyStagedChangeException
{
    /**
     * @param array<string> $missingAttributes
     */
    public static function forStagedChange(StagedChange $stagedChange, array $missingAttributes): self
    {
        return new self(sprintf(
            'Staged change [%s] requires manual resolution values for attributes [%s].',
            var_export($stagedChange->getKey(), true),
            implode(', ', $missingAttributes),
        ));
    }
}
