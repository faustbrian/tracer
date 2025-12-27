---
title: Diff Strategies
description: Learn how diff strategies control how model changes are calculated, stored, and applied in Tracer.
---

This guide covers diff strategies, which control how model changes are calculated and stored.

## What Are Diff Strategies?

Diff strategies determine:

- **How changes are calculated** between old and new values
- **How changes are stored** in the database
- **How changes are applied** when reverting or applying staged changes
- **How changes are described** in human-readable format

## Built-in Strategies

### Snapshot Diff Strategy

Stores complete old and new values for all changed attributes.

```php
// config/tracer.php
'default_diff_strategy' => SnapshotDiffStrategy::class,
```

**Storage Format:**

```php
$revision->old_values = ['title' => 'Old Title', 'content' => 'Old content'];
$revision->new_values = ['title' => 'New Title', 'content' => 'New content'];
```

**Pros:**
- Simple and reliable
- Easy to understand and debug
- Straightforward reversion

**Cons:**
- Uses more storage for large text fields
- Stores complete values even for small changes

### Attribute Diff Strategy

Stores per-attribute change metadata with type information.

```php
// config/tracer.php
'default_diff_strategy' => AttributeDiffStrategy::class,
```

**Storage Format:**

```php
$revision->old_values = [
    'title' => ['value' => 'Old Title', 'type' => 'string'],
    'count' => ['value' => 5, 'type' => 'integer'],
];
$revision->new_values = [
    'title' => ['value' => 'New Title', 'type' => 'string'],
    'count' => ['value' => 10, 'type' => 'integer'],
];
```

**Pros:**
- Preserves type information
- Better for complex data structures

**Cons:**
- Slightly more complex storage format
- More overhead for simple changes

## Per-Model Strategy Configuration

Configuration is managed via `config/tracer.php` or runtime registration, not on the model itself.

### Via Config File

```php
// config/tracer.php
'models' => [
    App\Models\Article::class => [
        'revision_diff_strategy' => AttributeDiffStrategy::class,
        'staged_diff_strategy' => SnapshotDiffStrategy::class,
    ],
],
```

### Via Runtime Registration

```php
use Cline\Tracer\Tracer;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;

// For revisions
Tracer::configure(Article::class)
    ->revisionDiffStrategy(AttributeDiffStrategy::class);

// For staged changes
Tracer::configure(Article::class)
    ->stagedDiffStrategy(AttributeDiffStrategy::class);
```

## Creating Custom Diff Strategies

### Step 1: Implement the Interface

```php
namespace App\Strategies\Diff;

use Cline\Tracer\Contracts\DiffStrategy;
use Illuminate\Database\Eloquent\Model;

class JsonPatchDiffStrategy implements DiffStrategy
{
    /**
     * Calculate the diff between old and new values.
     */
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $patches = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $patches[$key] = [
                'op' => $oldValue === null ? 'add' : 'replace',
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        // Handle removed keys
        foreach ($oldValues as $key => $oldValue) {
            if (!array_key_exists($key, $newValues)) {
                $patches[$key] = [
                    'op' => 'remove',
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $patches;
    }

    /**
     * Apply a stored diff to reconstruct values.
     */
    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $result = $currentValues;

        foreach ($diff as $key => $patch) {
            $targetValue = $reverse ? $patch['old'] : $patch['new'];

            if ($targetValue === null && isset($result[$key])) {
                unset($result[$key]);
            } else {
                $result[$key] = $targetValue;
            }
        }

        return $result;
    }

    /**
     * Get human-readable descriptions of changes.
     */
    public function describe(array $diff): array
    {
        $descriptions = [];

        foreach ($diff as $key => $patch) {
            $op = $patch['op'];

            $descriptions[$key] = match ($op) {
                'add' => sprintf('Added: "%s"', $this->format($patch['new'])),
                'remove' => sprintf('Removed: "%s"', $this->format($patch['old'])),
                'replace' => sprintf('Changed from "%s" to "%s"',
                    $this->format($patch['old']),
                    $this->format($patch['new'])
                ),
                default => 'Unknown change',
            };
        }

        return $descriptions;
    }

    /**
     * Get the unique identifier for this strategy.
     */
    public function identifier(): string
    {
        return 'json-patch';
    }

    private function format(mixed $value): string
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
```

### Step 2: Register the Strategy

```php
// config/tracer.php
'diff_strategies' => [
    'snapshot' => SnapshotDiffStrategy::class,
    'attribute' => AttributeDiffStrategy::class,
    'json-patch' => \App\Strategies\Diff\JsonPatchDiffStrategy::class,
],
```

Or at runtime:

```php
use Cline\Tracer\Tracer;

Tracer::registerDiffStrategy('json-patch', JsonPatchDiffStrategy::class);
```

### Step 3: Use the Strategy

```php
class Article extends Model implements Traceable
{
    use HasRevisions;

    protected string $revisionDiffStrategy = JsonPatchDiffStrategy::class;
}
```

## Strategy Examples

### Compact Text Diff Strategy

For large text fields, store only the differences:

```php
use SebastianBergmann\Diff\Differ;

class CompactTextDiffStrategy implements DiffStrategy
{
    private Differ $differ;

    public function __construct()
    {
        $this->differ = new Differ();
    }

    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $result = [
            'old' => [],
            'new' => [],
            'text_diffs' => [],
        ];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // For large text, store diff instead of full values
            if (is_string($newValue) && strlen($newValue) > 1000) {
                $result['text_diffs'][$key] = $this->differ->diff(
                    (string) $oldValue,
                    $newValue
                );
            } else {
                $result['old'][$key] = $oldValue;
                $result['new'][$key] = $newValue;
            }
        }

        return $result;
    }

    public function identifier(): string
    {
        return 'compact-text';
    }

    // ... implement apply() and describe()
}
```

### Encrypted Diff Strategy

For sensitive data that should be encrypted at rest:

```php
use Illuminate\Support\Facades\Crypt;

class EncryptedDiffStrategy implements DiffStrategy
{
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        return [
            'old' => Crypt::encryptString(json_encode($oldValues)),
            'new' => Crypt::encryptString(json_encode($newValues)),
            'encrypted' => true,
        ];
    }

    public function apply(array $currentValues, array $diff, bool $reverse = false): array
    {
        $targetKey = $reverse ? 'old' : 'new';
        $values = json_decode(Crypt::decryptString($diff[$targetKey]), true);

        return array_merge($currentValues, $values);
    }

    public function describe(array $diff): array
    {
        // Decrypt for description
        $old = json_decode(Crypt::decryptString($diff['old']), true);
        $new = json_decode(Crypt::decryptString($diff['new']), true);

        $descriptions = [];
        foreach (array_keys($new) as $key) {
            if (($old[$key] ?? null) !== $new[$key]) {
                $descriptions[$key] = 'Value changed (encrypted)';
            }
        }

        return $descriptions;
    }

    public function identifier(): string
    {
        return 'encrypted';
    }
}
```

### Semantic Diff Strategy

For JSON/array fields, provide detailed structure comparison:

```php
class SemanticDiffStrategy implements DiffStrategy
{
    public function calculate(array $oldValues, array $newValues, Model $model): array
    {
        $result = ['changes' => []];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if (is_array($newValue) && is_array($oldValue)) {
                // Deep comparison for arrays
                $result['changes'][$key] = $this->compareArrays($oldValue, $newValue);
            } else {
                $result['changes'][$key] = [
                    'type' => 'scalar',
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $result;
    }

    private function compareArrays(array $old, array $new): array
    {
        return [
            'type' => 'array',
            'added' => array_diff_key($new, $old),
            'removed' => array_diff_key($old, $new),
            'modified' => array_filter(
                array_intersect_key($new, $old),
                fn($v, $k) => $old[$k] !== $v,
                ARRAY_FILTER_USE_BOTH
            ),
        ];
    }

    public function identifier(): string
    {
        return 'semantic';
    }

    // ... implement apply() and describe()
}
```

## Resolving Strategies

### Get Strategy by Identifier

```php
use Cline\Tracer\Tracer;

$strategy = Tracer::resolveDiffStrategy('snapshot');
// Returns SnapshotDiffStrategy instance
```

### List Available Strategies

```php
$strategies = Tracer::getDiffStrategies();
// ['snapshot', 'attribute', 'json-patch', ...]
```

## Best Practices

1. **Choose the right strategy for your data**
   - Simple models: Use `snapshot` (default)
   - Large text fields: Consider custom compact strategy
   - Sensitive data: Use encrypted strategy

2. **Keep strategies consistent**
   - Don't change strategies on existing data
   - If you must change, plan a migration

3. **Test your custom strategies**
   - Verify `apply()` correctly reverses changes
   - Test with edge cases (nulls, empty arrays, etc.)

4. **Consider storage implications**
   - Large diffs impact database size
   - Consider pruning old revisions

## Next Steps

- **[Advanced Usage](advanced-usage)** - Events, pruning, and more
