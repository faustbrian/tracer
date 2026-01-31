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

/**
 * Fluent builder for model configuration.
 *
 * Provides a chainable API for configuring model-specific revision and staging
 * behavior. Each method call updates the configuration and saves it to the registry
 * immediately, allowing for progressive configuration building.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelConfigurationBuilder
{
    /**
     * Attributes to track for revisions (null = all).
     *
     * @var null|array<string>
     */
    private ?array $trackedAttributes = null;

    /**
     * Attributes to exclude from revision tracking.
     *
     * @var array<string>
     */
    private array $untrackedAttributes = [];

    /**
     * Diff strategy class for revisions.
     *
     * @var null|class-string<DiffStrategy>
     */
    private ?string $revisionDiffStrategy = null;

    /**
     * Attributes that can be staged (null = all).
     *
     * @var null|array<string>
     */
    private ?array $stageableAttributes = null;

    /**
     * Attributes that cannot be staged.
     *
     * @var array<string>
     */
    private array $unstageableAttributes = [];

    /**
     * Diff strategy class for staged changes.
     *
     * @var null|class-string<DiffStrategy>
     */
    private ?string $stagedDiffStrategy = null;

    /**
     * Approval strategy class for staged changes.
     *
     * @var null|class-string<ApprovalStrategy>
     */
    private ?string $approvalStrategy = null;

    /**
     * Create a new configuration builder.
     *
     * @param class-string               $modelClass The model class to configure
     * @param ModelConfigurationRegistry $registry   The registry to save configuration to
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly ModelConfigurationRegistry $registry,
    ) {}

    /**
     * Set the attributes to track for revisions.
     *
     * Specifies an explicit whitelist of attributes to include in revision tracking.
     * Only these attributes will be recorded when the model changes.
     *
     * @param  array<string> $attributes Attribute names to track
     * @return self          For method chaining
     */
    public function trackAttributes(array $attributes): self
    {
        $this->trackedAttributes = $attributes;
        $this->save();

        return $this;
    }

    /**
     * Set the attributes to exclude from revision tracking.
     *
     * Specifies attributes to exclude from revisions. These are combined with
     * global defaults to build the final exclusion list.
     *
     * @param  array<string> $attributes Attribute names to exclude
     * @return self          For method chaining
     */
    public function untrackAttributes(array $attributes): self
    {
        $this->untrackedAttributes = $attributes;
        $this->save();

        return $this;
    }

    /**
     * Set the diff strategy for revisions.
     *
     * Configures how revision diffs are calculated and stored for this model.
     *
     * @param  class-string<DiffStrategy> $strategy Diff strategy class name
     * @return self                       For method chaining
     */
    public function revisionDiffStrategy(string $strategy): self
    {
        $this->revisionDiffStrategy = $strategy;
        $this->save();

        return $this;
    }

    /**
     * Set the attributes that can be staged.
     *
     * Specifies an explicit whitelist of attributes that can be included in
     * staged changes for approval workflows.
     *
     * @param  array<string> $attributes Attribute names that can be staged
     * @return self          For method chaining
     */
    public function stageableAttributes(array $attributes): self
    {
        $this->stageableAttributes = $attributes;
        $this->save();

        return $this;
    }

    /**
     * Set the attributes that cannot be staged.
     *
     * Specifies attributes to exclude from staging. These are combined with
     * global defaults to prevent sensitive data from entering approval workflows.
     *
     * @param  array<string> $attributes Attribute names that cannot be staged
     * @return self          For method chaining
     */
    public function unstageableAttributes(array $attributes): self
    {
        $this->unstageableAttributes = $attributes;
        $this->save();

        return $this;
    }

    /**
     * Set the diff strategy for staged changes.
     *
     * Configures how diffs are calculated and stored for staged changes.
     * May differ from revision diff strategy to support different workflows.
     *
     * @param  class-string<DiffStrategy> $strategy Diff strategy class name
     * @return self                       For method chaining
     */
    public function stagedDiffStrategy(string $strategy): self
    {
        $this->stagedDiffStrategy = $strategy;
        $this->save();

        return $this;
    }

    /**
     * Set the approval strategy for staged changes.
     *
     * Configures the approval workflow for this model (single approver,
     * multi-step, quorum-based, etc.).
     *
     * @param  class-string<ApprovalStrategy> $strategy Approval strategy class name
     * @return self                           For method chaining
     */
    public function approvalStrategy(string $strategy): self
    {
        $this->approvalStrategy = $strategy;
        $this->save();

        return $this;
    }

    /**
     * Build and return the configuration.
     *
     * Creates a new ModelConfiguration instance from the current builder state.
     *
     * @return ModelConfiguration The built configuration instance
     */
    public function build(): ModelConfiguration
    {
        return new ModelConfiguration(
            modelClass: $this->modelClass,
            trackedAttributes: $this->trackedAttributes,
            untrackedAttributes: $this->untrackedAttributes,
            revisionDiffStrategy: $this->revisionDiffStrategy,
            stageableAttributes: $this->stageableAttributes,
            unstageableAttributes: $this->unstageableAttributes,
            stagedDiffStrategy: $this->stagedDiffStrategy,
            approvalStrategy: $this->approvalStrategy,
        );
    }

    /**
     * Save the current configuration to the registry.
     *
     * Called automatically after each configuration method to persist changes
     * immediately, allowing configuration to be accessed mid-build.
     */
    private function save(): void
    {
        $this->registry->register($this->build());
    }
}
