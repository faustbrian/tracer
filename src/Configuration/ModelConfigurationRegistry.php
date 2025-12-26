<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Configuration;

use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Contracts\DiffStrategy;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Facades\Config;

use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_merge;
use function array_unique;

/**
 * Registry for model-specific configuration.
 *
 * Configuration can be set via:
 * 1. config/tracer.php 'models' array
 * 2. Runtime registration via Tracer::configure()
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class ModelConfigurationRegistry
{
    /**
     * Registered model configurations.
     *
     * Stores configuration instances keyed by model class name. Populated from
     * config/tracer.php on first access and runtime registrations.
     *
     * @var array<class-string, ModelConfiguration>
     */
    private array $configurations = [];

    /**
     * Whether config has been loaded.
     *
     * Tracks if configuration from config/tracer.php has been loaded to avoid
     * redundant file access.
     */
    private bool $configLoaded = false;

    /**
     * Get or create a configuration builder for a model.
     *
     * Returns a fluent builder for configuring the specified model. The builder
     * will save changes back to this registry automatically.
     *
     * @param  class-string              $modelClass The model class to configure
     * @return ModelConfigurationBuilder Fluent builder for configuration
     */
    public function configure(string $modelClass): ModelConfigurationBuilder
    {
        return new ModelConfigurationBuilder($modelClass, $this);
    }

    /**
     * Register a model configuration.
     *
     * Stores or updates the configuration for a model class. Called by the
     * configuration builder or when loading from config files.
     *
     * @param ModelConfiguration $configuration The configuration to register
     */
    public function register(ModelConfiguration $configuration): void
    {
        $this->configurations[$configuration->modelClass] = $configuration;
    }

    /**
     * Get configuration for a model class.
     *
     * Returns the configuration instance if one exists, loading from config file
     * on first access. Returns null if no configuration is registered.
     *
     * @param  class-string            $modelClass The model class to get configuration for
     * @return null|ModelConfiguration The configuration instance, or null if not found
     */
    public function get(string $modelClass): ?ModelConfiguration
    {
        $this->ensureConfigLoaded();

        return $this->configurations[$modelClass] ?? null;
    }

    /**
     * Check if a model has configuration.
     *
     * @param  class-string $modelClass The model class to check
     * @return bool         Whether configuration exists for this model
     */
    public function has(string $modelClass): bool
    {
        $this->ensureConfigLoaded();

        return isset($this->configurations[$modelClass]);
    }

    /**
     * Get tracked attributes for a model.
     *
     * Applies model-specific and global configuration to determine which attributes
     * should be included in revision tracking. Respects both whitelist (trackedAttributes)
     * and blacklist (untrackedAttributes) rules.
     *
     * @param  class-string         $modelClass      The model class
     * @param  array<string, mixed> $modelAttributes Current model attributes
     * @return array<string, mixed> Filtered attributes to track
     */
    public function getTrackedAttributes(string $modelClass, array $modelAttributes): array
    {
        $this->ensureConfigLoaded();

        $config = $this->configurations[$modelClass] ?? null;

        // Start with model attributes
        $attributes = $modelAttributes;

        // If specific tracked attributes defined, filter to only those
        if ($config?->trackedAttributes !== null) {
            $attributes = array_intersect_key($attributes, array_flip($config->trackedAttributes));
        }

        // Remove untracked attributes (model-specific + global defaults)
        $untracked = $this->getUntrackedAttributes($modelClass);

        return array_diff_key($attributes, array_flip($untracked));
    }

    /**
     * Get untracked attributes for a model.
     *
     * Combines global default exclusions (id, timestamps, etc.) with model-specific
     * exclusions to build the complete list of attributes to exclude from tracking.
     *
     * @param  class-string  $modelClass The model class
     * @return array<string> Combined list of attributes to exclude
     */
    public function getUntrackedAttributes(string $modelClass): array
    {
        $this->ensureConfigLoaded();

        /** @var array<string> $globalDefaults */
        $globalDefaults = Config::get('tracer.untracked_attributes', [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token',
        ]);

        $config = $this->configurations[$modelClass] ?? null;
        $modelUntracked = $config !== null ? $config->untrackedAttributes : [];

        return array_unique(array_merge($globalDefaults, $modelUntracked));
    }

    /**
     * Get the revision diff strategy for a model.
     *
     * Returns the model-specific revision diff strategy if configured, or null
     * to use the global default.
     *
     * @param  class-string                    $modelClass The model class
     * @return null|class-string<DiffStrategy> The configured strategy class, or null for default
     */
    public function getRevisionDiffStrategy(string $modelClass): ?string
    {
        $this->ensureConfigLoaded();

        return ($this->configurations[$modelClass] ?? null)?->revisionDiffStrategy;
    }

    /**
     * Get stageable attributes for a model.
     *
     * Applies model-specific and global configuration to determine which attributes
     * can be included in staged changes. Respects both whitelist (stageableAttributes)
     * and blacklist (unstageableAttributes) rules.
     *
     * @param  class-string         $modelClass         The model class
     * @param  array<string, mixed> $proposedAttributes Proposed attribute changes
     * @return array<string, mixed> Filtered attributes that can be staged
     */
    public function getStageableAttributes(string $modelClass, array $proposedAttributes): array
    {
        $this->ensureConfigLoaded();

        $config = $this->configurations[$modelClass] ?? null;

        // Start with proposed attributes
        $attributes = $proposedAttributes;

        // If specific stageable attributes defined, filter to only those
        if ($config?->stageableAttributes !== null) {
            $attributes = array_intersect_key($attributes, array_flip($config->stageableAttributes));
        }

        // Remove unstageable attributes (model-specific + global defaults)
        $unstageable = $this->getUnstageableAttributes($modelClass);

        return array_diff_key($attributes, array_flip($unstageable));
    }

    /**
     * Get unstageable attributes for a model.
     *
     * Combines global default exclusions (id, timestamps, etc.) with model-specific
     * exclusions to build the complete list of attributes that cannot be staged.
     *
     * @param  class-string  $modelClass The model class
     * @return array<string> Combined list of attributes that cannot be staged
     */
    public function getUnstageableAttributes(string $modelClass): array
    {
        $this->ensureConfigLoaded();

        /** @var array<string> $globalDefaults */
        $globalDefaults = Config::get('tracer.unstageable_attributes', [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $config = $this->configurations[$modelClass] ?? null;
        $modelUnstageable = $config !== null ? $config->unstageableAttributes : [];

        return array_unique(array_merge($globalDefaults, $modelUnstageable));
    }

    /**
     * Get the staged diff strategy for a model.
     *
     * Returns the model-specific staged change diff strategy if configured, or
     * null to use the global default.
     *
     * @param  class-string                    $modelClass The model class
     * @return null|class-string<DiffStrategy> The configured strategy class, or null for default
     */
    public function getStagedDiffStrategy(string $modelClass): ?string
    {
        $this->ensureConfigLoaded();

        return ($this->configurations[$modelClass] ?? null)?->stagedDiffStrategy;
    }

    /**
     * Get the approval strategy for a model.
     *
     * Returns the model-specific approval strategy if configured, or null to use
     * the global default.
     *
     * @param  class-string                        $modelClass The model class
     * @return null|class-string<ApprovalStrategy> The configured strategy class, or null for default
     */
    public function getApprovalStrategy(string $modelClass): ?string
    {
        $this->ensureConfigLoaded();

        return ($this->configurations[$modelClass] ?? null)?->approvalStrategy;
    }

    /**
     * Clear all registered configurations.
     *
     * Resets the registry to its initial state, removing all configurations and
     * marking config as not loaded. Useful for testing or reinitialization.
     */
    public function clear(): void
    {
        $this->configurations = [];
        $this->configLoaded = false;
    }

    /**
     * Load configurations from config file if not already loaded.
     *
     * Lazily loads model configurations from config/tracer.php on first access.
     * Runtime configurations take precedence and are not overwritten by file config.
     */
    private function ensureConfigLoaded(): void
    {
        if ($this->configLoaded) {
            return;
        }

        /** @var array<class-string, array<string, mixed>> $models */
        $models = Config::get('tracer.models', []);

        foreach ($models as $modelClass => $config) {
            // Don't overwrite runtime configurations
            if (isset($this->configurations[$modelClass])) {
                continue;
            }

            $this->configurations[$modelClass] = ModelConfiguration::fromArray($modelClass, $config);
        }

        $this->configLoaded = true;
    }
}
