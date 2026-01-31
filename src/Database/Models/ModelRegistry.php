<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Database\Models;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Registry for managing polymorphic model type mappings.
 *
 * Centralizes control over how model class names are stored in polymorphic
 * relationships. Instead of storing full class names (e.g., "App\Models\User"),
 * this registry maps them to shorter, more stable aliases (e.g., "user").
 *
 * This provides database portability, cleaner debugging, and protection against
 * refactoring that changes class names or namespaces. The singleton pattern
 * ensures consistent mappings across the entire application lifecycle.
 *
 * ```php
 * $registry = app(ModelRegistry::class);
 *
 * // Optional: define mappings
 * $registry->morphKeyMap([
 *     User::class => 'user',
 *     Article::class => 'article',
 * ]);
 *
 * // Or enforce mappings (prevents unmapped models)
 * $registry->enforceMorphKeyMap([
 *     User::class => 'user',
 *     Article::class => 'article',
 * ]);
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class ModelRegistry
{
    /**
     * Register a morph map, merging with any existing mappings.
     *
     * Defines aliases for model class names used in polymorphic relationships.
     * New mappings are merged with existing ones, allowing incremental registration
     * across multiple service providers or configuration files.
     *
     * @param array<class-string, string> $map Associative array mapping fully-qualified
     *                                         class names to short string aliases
     */
    public function morphKeyMap(array $map): void
    {
        Relation::morphMap($map);
    }

    /**
     * Register and enforce a morph map, requiring all polymorphic relationships to use the map.
     *
     * Defines aliases and activates strict mode, throwing exceptions if any polymorphic
     * relationship attempts to use a model class that hasn't been explicitly mapped.
     * This prevents accidental storage of full class names and ensures all relationships
     * use consistent, portable aliases.
     *
     * @param array<class-string, string> $map Associative array mapping fully-qualified
     *                                         class names to short string aliases
     */
    public function enforceMorphKeyMap(array $map): void
    {
        Relation::requireMorphMap();
        Relation::morphMap($map);
    }
}
