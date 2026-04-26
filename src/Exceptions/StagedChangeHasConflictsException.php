<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Exceptions;

use Cline\Tracer\Database\Models\StagedChange;

use function array_keys;
use function implode;
use function sprintf;
use function var_export;

/**
 * Thrown when a staged change cannot be applied because its target drifted.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeHasConflictsException extends CannotApplyStagedChangeException
{
    /**
     * @param array<string, array<string, mixed>> $conflicts
     */
    public static function forStagedChange(StagedChange $stagedChange, array $conflicts): self
    {
        return new self(sprintf(
            'Staged change [%s] has unresolved conflicts for attributes [%s]. Resolve them before applying.',
            var_export($stagedChange->getKey(), true),
            implode(', ', array_keys($conflicts)),
        ));
    }
}
