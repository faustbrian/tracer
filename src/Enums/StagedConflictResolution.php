<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Enums;

/**
 * Conflict resolution modes for staged changes whose target has drifted.
 *
 * These modes mirror familiar source control semantics:
 * - ours: keep the currently persisted value for conflicted attributes
 * - theirs: force the staged proposed value for conflicted attributes
 * - manual: use an explicit caller-provided resolved value per conflict
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum StagedConflictResolution: string
{
    case Ours = 'ours';
    case Theirs = 'theirs';
    case Manual = 'manual';
}
