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

use function array_keys;
use function array_merge;
use function array_unique;
use function is_array;
use function is_bool;
use function is_scalar;
use function json_encode;
use function sprintf;

/**
 * Full snapshot diff strategy for complete state preservation.
 *
 * Stores complete snapshots of both old and new attribute values for every change,
 * regardless of which attributes actually changed. This strategy prioritizes simplicity
 * and reliability over storage efficiency, making it ideal for models with few attributes
 * or scenarios where complete historical state is critical.
 *
 * Unlike the attribute diff strategy which stores only changes, snapshots preserve the
 * entire state at each point in time, making reversion and state reconstruction trivial
 * at the cost of increased storage requirements.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SnapshotDiffStrategy implements DiffStrategy
{
    /**
     * Calculate the diff by storing complete old and new snapshots.
     *
     * Creates a diff containing complete attribute snapshots for both the old state
     * (before changes) and new state (after changes). This approach stores all attributes
     * regardless of whether they changed, making the diff structure simple and predictable.
     * The model parameter is available for context but not used in this implementation.
     *
     * @param  array<string, mixed> $oldValues The complete set of attribute values before the change
     * @param  array<string, mixed> $newValues The complete set of attribute values after the change
     * @param  Model                $model     The model instance being tracked (available for context)
     * @return array<string, mixed> Array with 'old' and 'new' keys containing complete attribute snapshots
     */
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        return [
            'old' => $oldValues,
            'new' => $newValues,
        ];
    }

    /**
     * Apply a stored diff to reconstruct complete attribute values.
     *
     * Merges the appropriate snapshot (old or new) with current values to reconstruct
     * the desired state. When reversing, uses the 'old' snapshot to restore previous state.
     * When applying forward, uses the 'new' snapshot. Falls back to the entire diff if
     * the expected structure is not present for backward compatibility.
     *
     * @param  array<string, mixed> $currentValues The current model attribute values to merge with
     * @param  array<string, mixed> $diff          The diff data containing 'old' and 'new' snapshots
     * @param  bool                 $reverse       Whether to apply the diff in reverse (revert to old snapshot)
     * @return array<string, mixed> The reconstructed attribute values after applying the snapshot
     */
    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $targetValues = $reverse
            ? ($diff['old'] ?? [])
            : ($diff['new'] ?? $diff);

        return array_merge($currentValues, $targetValues);
    }

    /**
     * Generate human-readable descriptions of changes between snapshots.
     *
     * Compares the old and new snapshots to identify which attributes changed and
     * generates natural language descriptions of those changes. Handles different
     * change types: new values being set, existing values being cleared, and values
     * changing from one value to another.
     *
     * @param  array<string, mixed>  $diff The diff data containing 'old' and 'new' snapshots
     * @return array<string, string> Keyed array where each attribute name maps to its change description
     */
    public function describe(array $diff): array
    {
        $descriptions = [];

        /** @var array<string, mixed> $old */
        $old = $diff['old'] ?? [];

        /** @var array<string, mixed> $new */
        $new = $diff['new'] ?? $diff;

        /** @var array<string> $allKeys */
        $allKeys = array_unique([...array_keys($old), ...array_keys($new)]);

        foreach ($allKeys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue === null && $newValue !== null) {
                $descriptions[$key] = sprintf('Set to "%s"', $this->formatValue($newValue));
            } elseif ($oldValue !== null && $newValue === null) {
                $descriptions[$key] = sprintf('Cleared (was "%s")', $this->formatValue($oldValue));
            } elseif ($oldValue !== $newValue) {
                $descriptions[$key] = sprintf(
                    'Changed from "%s" to "%s"',
                    $this->formatValue($oldValue),
                    $this->formatValue($newValue),
                );
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
        return 'snapshot';
    }

    /**
     * Format a value for human-readable display in change descriptions.
     *
     * Converts various value types to string representations suitable for display:
     * null becomes 'null', booleans become 'true'/'false', arrays are JSON encoded,
     * scalar values are cast to string, and complex objects are JSON encoded with
     * fallback to empty string on encoding failure.
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
            return json_encode($value) ?: '[]';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value) ?: '';
    }
}
