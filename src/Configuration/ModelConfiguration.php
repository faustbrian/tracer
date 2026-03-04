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
 * Configuration for a specific model's revision and staging behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class ModelConfiguration
{
    /**
     * Create a new model configuration instance.
     *
     * @param class-string                        $modelClass            The fully-qualified model class name that this configuration applies to.
     *                                                                   Used to look up configuration when processing revisions and staged changes.
     * @param null|array<string>                  $trackedAttributes     Optional whitelist of attributes to track in revisions.
     *                                                                   When null, all attributes are tracked (except those in untrackedAttributes).
     *                                                                   When specified, only listed attributes are included in revision tracking.
     * @param array<string>                       $untrackedAttributes   Attributes to exclude from revision tracking. These are combined with
     *                                                                   global defaults (id, created_at, updated_at, etc.) to build the final
     *                                                                   exclusion list. Use this to exclude sensitive or irrelevant data.
     * @param null|class-string<DiffStrategy>     $revisionDiffStrategy  Optional diff strategy class for revision tracking.
     *                                                                   When null, uses the global default diff strategy.
     *                                                                   Determines how attribute changes are stored and reconstructed.
     * @param null|array<string>                  $stageableAttributes   Optional whitelist of attributes that can be staged for approval.
     *                                                                   When null, all attributes are stageable (except unstageableAttributes).
     *                                                                   When specified, only listed attributes can be included in staged changes.
     * @param array<string>                       $unstageableAttributes Attributes that cannot be staged for approval. Combined with global
     *                                                                   defaults to prevent sensitive or system-managed attributes from being
     *                                                                   modified through the staging workflow.
     * @param null|class-string<DiffStrategy>     $stagedDiffStrategy    Optional diff strategy class for staged changes.
     *                                                                   When null, uses the global default. May differ from
     *                                                                   revisionDiffStrategy to support different staging workflows.
     * @param null|class-string<ApprovalStrategy> $approvalStrategy      Optional approval strategy class for staged changes.
     *                                                                   When null, uses the global default approval strategy.
     *                                                                   Determines the approval workflow (single, multi-step, etc.).
     */
    public function __construct(
        public string $modelClass,
        public ?array $trackedAttributes = null,
        public array $untrackedAttributes = [],
        public ?string $revisionDiffStrategy = null,
        public ?array $stageableAttributes = null,
        public array $unstageableAttributes = [],
        public ?string $stagedDiffStrategy = null,
        public ?string $approvalStrategy = null,
    ) {}

    /**
     * Create from an array of configuration values.
     *
     * Factory method for creating configuration from array data, typically loaded
     * from the config/tracer.php file. Maps array keys to constructor parameters.
     *
     * @param  class-string         $modelClass The model class to configure
     * @param  array<string, mixed> $config     Configuration array with keys matching constructor parameters
     * @return self                 The constructed configuration instance
     */
    public static function fromArray(string $modelClass, array $config): self
    {
        /** @var null|array<string> $trackedAttributes */
        $trackedAttributes = $config['tracked_attributes'] ?? null;

        /** @var array<string> $untrackedAttributes */
        $untrackedAttributes = $config['untracked_attributes'] ?? [];

        /** @var null|class-string<DiffStrategy> $revisionDiffStrategy */
        $revisionDiffStrategy = $config['revision_diff_strategy'] ?? null;

        /** @var null|array<string> $stageableAttributes */
        $stageableAttributes = $config['stageable_attributes'] ?? null;

        /** @var array<string> $unstageableAttributes */
        $unstageableAttributes = $config['unstageable_attributes'] ?? [];

        /** @var null|class-string<DiffStrategy> $stagedDiffStrategy */
        $stagedDiffStrategy = $config['staged_diff_strategy'] ?? null;

        /** @var null|class-string<ApprovalStrategy> $approvalStrategy */
        $approvalStrategy = $config['approval_strategy'] ?? null;

        return new self(
            modelClass: $modelClass,
            trackedAttributes: $trackedAttributes,
            untrackedAttributes: $untrackedAttributes,
            revisionDiffStrategy: $revisionDiffStrategy,
            stageableAttributes: $stageableAttributes,
            unstageableAttributes: $unstageableAttributes,
            stagedDiffStrategy: $stagedDiffStrategy,
            approvalStrategy: $approvalStrategy,
        );
    }
}
