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
 * Contract for strategies that calculate and store model diffs.
 *
 * Implementations determine how changes between model states are represented,
 * whether as full snapshots, partial diffs, JSON patches, or custom formats.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface DiffStrategy
{
    /**
     * Calculate the diff between the old and new state.
     *
     * @param  array<string, mixed> $oldValues The original attribute values
     * @param  array<string, mixed> $newValues The new attribute values
     * @param  Model                $model     The model being diffed (for context)
     * @return array<string, mixed> The calculated diff in the strategy's format
     */
    public function calculate(array $oldValues, array $newValues, Model $model): array;

    /**
     * Apply a stored diff to reconstruct the original or new state.
     *
     * @param  array<string, mixed> $currentValues The current values to apply diff to
     * @param  array<string, mixed> $diff          The stored diff to apply
     * @param  bool                 $reverse       Whether to reverse the diff (going backward in history)
     * @return array<string, mixed> The resulting attribute values
     */
    public function apply(array $currentValues, array $diff, bool $reverse = false): array;

    /**
     * Get a human-readable description of the changes.
     *
     * @param  array<string, mixed>  $diff The stored diff
     * @return array<string, string> Key-value pairs describing each change
     */
    public function describe(array $diff): array;

    /**
     * Get the unique identifier for this strategy.
     *
     * Used to store which strategy was used for a given revision/staged change,
     * allowing the system to use the correct strategy when applying or reverting.
     */
    public function identifier(): string;
}
