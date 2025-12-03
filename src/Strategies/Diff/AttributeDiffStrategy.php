<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Strategies\Diff;

use Cline\Tracer\Contracts\DiffStrategy;
use Illuminate\Database\Eloquent\Model;

use function array_key_exists;
use function array_keys;
use function array_unique;
use function is_array;
use function is_bool;
use function json_encode;
use function sprintf;

/**
 * Attribute-level diff strategy for space-efficient change tracking.
 *
 * Stores only the specific attributes that changed, with their old and new values,
 * rather than capturing complete model snapshots. This approach is more storage-efficient
 * for models with many attributes where only a few fields typically change at once.
 *
 * The diff format is a keyed array where each changed attribute maps to an object
 * containing 'old' and 'new' values. This allows for precise reversion and clear
 * visualization of what changed without storing redundant unchanged data.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AttributeDiffStrategy implements DiffStrategy
{
    /**
     * Calculate the diff by comparing old and new attribute values.
     *
     * Iterates through all attributes present in either the old or new state and
     * records those that differ. Unchanged attributes are excluded from the diff
     * to minimize storage requirements. The model parameter is available for
     * context but not used in this implementation.
     *
     * @param  array<string, mixed> $oldValues The previous attribute values before the change
     * @param  array<string, mixed> $newValues The current attribute values after the change
     * @param  Model                $model     The model instance being tracked (available for context)
     * @return array<string, mixed> Keyed array where each key is an attribute name and value is ['old' => ..., 'new' => ...]
     */
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $changes = [];
        $allKeys = array_unique([...array_keys($oldValues), ...array_keys($newValues)]);

        foreach ($allKeys as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    /**
     * Apply a stored diff to reconstruct attribute values.
     *
     * Merges the diff with current values to reconstruct either the new state (forward)
     * or the old state (reverse). When reversing, applies the 'old' values from the diff.
     * When applying forward, uses the 'new' values. Handles both structured diffs with
     * 'old' and 'new' keys, as well as simple value diffs for backward compatibility.
     *
     * @param  array<string, mixed> $currentValues The current model attribute values to apply changes to
     * @param  array<string, mixed> $diff          The diff data containing change information
     * @param  bool                 $reverse       Whether to apply the diff in reverse (revert to old values)
     * @return array<string, mixed> The reconstructed attribute values after applying the diff
     */
    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $result = $currentValues;

        foreach ($diff as $key => $change) {
            if (is_array($change) && array_key_exists('old', $change) && array_key_exists('new', $change)) {
                $result[$key] = $reverse ? $change['old'] : $change['new'];
            } else {
                // Handle simple value diffs
                $result[$key] = $change;
            }
        }

        return $result;
    }

    /**
     * Generate human-readable descriptions of attribute changes.
     *
     * Converts the technical diff structure into user-friendly descriptions that
     * explain what changed in natural language. Handles different change types:
     * new values being set, existing values being cleared, and values changing
     * from one value to another.
     *
     * @param  array<string, mixed>  $diff The diff data to generate descriptions for
     * @return array<string, string> Keyed array where each attribute name maps to its change description
     */
    public function describe(array $diff): array
    {
        $descriptions = [];

        foreach ($diff as $key => $change) {
            if (is_array($change) && array_key_exists('old', $change) && array_key_exists('new', $change)) {
                $oldValue = $change['old'];
                $newValue = $change['new'];

                if ($oldValue === null && $newValue !== null) {
                    $descriptions[$key] = sprintf('Set to "%s"', $this->formatValue($newValue));
                } elseif ($oldValue !== null && $newValue === null) {
                    $descriptions[$key] = sprintf('Cleared (was "%s")', $this->formatValue($oldValue));
                } else {
                    $descriptions[$key] = sprintf(
                        'Changed from "%s" to "%s"',
                        $this->formatValue($oldValue),
                        $this->formatValue($newValue),
                    );
                }
            } else {
                $descriptions[$key] = sprintf('Set to "%s"', $this->formatValue($change));
            }
        }

        return $descriptions;
    }

    /**
     * Get the unique identifier for this strategy.
     *
     * @return string The strategy identifier used in configuration
     */
    public function identifier(): string
    {
        return 'attribute';
    }

    /**
     * Format a value for human-readable display in change descriptions.
     *
     * Converts various value types to string representations suitable for display:
     * null becomes 'null', booleans become 'true'/'false', arrays are JSON encoded,
     * and other values are cast to string.
     *
     * @param  mixed  $value The value to format for display
     * @return string The formatted string representation of the value
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }
}
