<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for resolving the entity responsible for changes.
 *
 * Implementations determine who caused a revision or authored a staged change.
 * The default implementation uses the authenticated user, but custom
 * implementations can resolve causers from other sources (queue jobs,
 * console commands, API tokens, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface CauserResolver
{
    /**
     * Resolve the entity responsible for the current change.
     *
     * @return null|Model The model representing the causer, or null if anonymous
     */
    public function resolve(): ?Model;
}
