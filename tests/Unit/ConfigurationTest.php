<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Configuration\ModelConfiguration;
use Cline\Tracer\Configuration\ModelConfigurationBuilder;
use Cline\Tracer\Configuration\ModelConfigurationRegistry;
use Cline\Tracer\Strategies\Approval\QuorumApprovalStrategy;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;
use Cline\Tracer\Strategies\Diff\SnapshotDiffStrategy;
use Tests\Fixtures\Article;

describe('Configuration', function (): void {
    describe('ModelConfiguration', function (): void {
        test('creates configuration with default values', function (): void {
            $config = new ModelConfiguration(Article::class);

            expect($config->modelClass)->toBe(Article::class);
            expect($config->trackedAttributes)->toBeNull();
            expect($config->untrackedAttributes)->toBe([]);
            expect($config->revisionDiffStrategy)->toBeNull();
            expect($config->stageableAttributes)->toBeNull();
            expect($config->unstageableAttributes)->toBe([]);
            expect($config->stagedDiffStrategy)->toBeNull();
            expect($config->approvalStrategy)->toBeNull();
        });

        test('creates configuration with all parameters', function (): void {
            $config = new ModelConfiguration(
                modelClass: Article::class,
                trackedAttributes: ['title', 'content'],
                untrackedAttributes: ['internal_notes'],
                revisionDiffStrategy: AttributeDiffStrategy::class,
                stageableAttributes: ['title'],
                unstageableAttributes: ['admin_only'],
                stagedDiffStrategy: SnapshotDiffStrategy::class,
                approvalStrategy: QuorumApprovalStrategy::class,
            );

            expect($config->modelClass)->toBe(Article::class);
            expect($config->trackedAttributes)->toBe(['title', 'content']);
            expect($config->untrackedAttributes)->toBe(['internal_notes']);
            expect($config->revisionDiffStrategy)->toBe(AttributeDiffStrategy::class);
            expect($config->stageableAttributes)->toBe(['title']);
            expect($config->unstageableAttributes)->toBe(['admin_only']);
            expect($config->stagedDiffStrategy)->toBe(SnapshotDiffStrategy::class);
            expect($config->approvalStrategy)->toBe(QuorumApprovalStrategy::class);
        });

        test('creates configuration from array', function (): void {
            $config = ModelConfiguration::fromArray(Article::class, [
                'tracked_attributes' => ['title', 'content'],
                'untracked_attributes' => ['internal_notes'],
                'revision_diff_strategy' => AttributeDiffStrategy::class,
                'stageable_attributes' => ['title'],
                'unstageable_attributes' => ['admin_only'],
                'staged_diff_strategy' => SnapshotDiffStrategy::class,
                'approval_strategy' => QuorumApprovalStrategy::class,
            ]);

            expect($config->modelClass)->toBe(Article::class);
            expect($config->trackedAttributes)->toBe(['title', 'content']);
            expect($config->untrackedAttributes)->toBe(['internal_notes']);
            expect($config->revisionDiffStrategy)->toBe(AttributeDiffStrategy::class);
            expect($config->stageableAttributes)->toBe(['title']);
            expect($config->unstageableAttributes)->toBe(['admin_only']);
            expect($config->stagedDiffStrategy)->toBe(SnapshotDiffStrategy::class);
            expect($config->approvalStrategy)->toBe(QuorumApprovalStrategy::class);
        });

        test('creates configuration from empty array', function (): void {
            $config = ModelConfiguration::fromArray(Article::class, []);

            expect($config->modelClass)->toBe(Article::class);
            expect($config->trackedAttributes)->toBeNull();
            expect($config->untrackedAttributes)->toBe([]);
        });
    });

    describe('ModelConfigurationBuilder', function (): void {
        test('builds configuration with tracked attributes', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->trackAttributes(['title', 'content']);

            $config = $registry->get(Article::class);
            expect($config->trackedAttributes)->toBe(['title', 'content']);
        });

        test('builds configuration with untracked attributes', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->untrackAttributes(['internal_notes']);

            $config = $registry->get(Article::class);
            expect($config->untrackedAttributes)->toBe(['internal_notes']);
        });

        test('builds configuration with revision diff strategy', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->revisionDiffStrategy(AttributeDiffStrategy::class);

            $config = $registry->get(Article::class);
            expect($config->revisionDiffStrategy)->toBe(AttributeDiffStrategy::class);
        });

        test('builds configuration with stageable attributes', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->stageableAttributes(['title']);

            $config = $registry->get(Article::class);
            expect($config->stageableAttributes)->toBe(['title']);
        });

        test('builds configuration with unstageable attributes', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->unstageableAttributes(['admin_only']);

            $config = $registry->get(Article::class);
            expect($config->unstageableAttributes)->toBe(['admin_only']);
        });

        test('builds configuration with staged diff strategy', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->stagedDiffStrategy(SnapshotDiffStrategy::class);

            $config = $registry->get(Article::class);
            expect($config->stagedDiffStrategy)->toBe(SnapshotDiffStrategy::class);
        });

        test('builds configuration with approval strategy', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->approvalStrategy(QuorumApprovalStrategy::class);

            $config = $registry->get(Article::class);
            expect($config->approvalStrategy)->toBe(QuorumApprovalStrategy::class);
        });

        test('chains multiple configuration options', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder
                ->trackAttributes(['title', 'content'])
                ->untrackAttributes(['internal_notes'])
                ->revisionDiffStrategy(AttributeDiffStrategy::class)
                ->stageableAttributes(['title'])
                ->approvalStrategy(QuorumApprovalStrategy::class);

            $config = $registry->get(Article::class);
            expect($config->trackedAttributes)->toBe(['title', 'content']);
            expect($config->untrackedAttributes)->toBe(['internal_notes']);
            expect($config->revisionDiffStrategy)->toBe(AttributeDiffStrategy::class);
            expect($config->stageableAttributes)->toBe(['title']);
            expect($config->approvalStrategy)->toBe(QuorumApprovalStrategy::class);
        });

        test('builds configuration object', function (): void {
            $registry = new ModelConfigurationRegistry();
            $builder = new ModelConfigurationBuilder(Article::class, $registry);

            $builder->trackAttributes(['title']);

            $config = $builder->build();

            expect($config)->toBeInstanceOf(ModelConfiguration::class);
            expect($config->trackedAttributes)->toBe(['title']);
        });
    });

    describe('ModelConfigurationRegistry', function (): void {
        test('registers and retrieves configuration', function (): void {
            $registry = new ModelConfigurationRegistry();
            $config = new ModelConfiguration(Article::class, trackedAttributes: ['title']);

            $registry->register($config);

            expect($registry->has(Article::class))->toBeTrue();
            expect($registry->get(Article::class))->toBe($config);
        });

        test('returns null for unregistered model', function (): void {
            $registry = new ModelConfigurationRegistry();

            expect($registry->has(Article::class))->toBeFalse();
            expect($registry->get(Article::class))->toBeNull();
        });

        test('configure returns builder', function (): void {
            $registry = new ModelConfigurationRegistry();

            $builder = $registry->configure(Article::class);

            expect($builder)->toBeInstanceOf(ModelConfigurationBuilder::class);
        });

        test('clears all configurations', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(Article::class),
            );

            $registry->clear();

            expect($registry->has(Article::class))->toBeFalse();
        });

        test('gets tracked attributes with model filter', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    trackedAttributes: ['title', 'content'],
                ),
            );

            $attributes = $registry->getTrackedAttributes(Article::class, [
                'title' => 'Test',
                'content' => 'Content',
                'status' => 'draft',
            ]);

            expect($attributes)->toBe(['title' => 'Test', 'content' => 'Content']);
        });

        test('gets tracked attributes excluding untracked', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    untrackedAttributes: ['internal_notes'],
                ),
            );

            $attributes = $registry->getTrackedAttributes(Article::class, [
                'title' => 'Test',
                'internal_notes' => 'Secret',
            ]);

            expect($attributes)->toBe(['title' => 'Test']);
            expect($attributes)->not->toHaveKey('internal_notes');
        });

        test('gets untracked attributes with global defaults', function (): void {
            $registry = new ModelConfigurationRegistry();

            $untracked = $registry->getUntrackedAttributes(Article::class);

            expect($untracked)->toContain('id', 'created_at', 'updated_at', 'deleted_at');
        });

        test('gets untracked attributes with model additions', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    untrackedAttributes: ['custom_field'],
                ),
            );

            $untracked = $registry->getUntrackedAttributes(Article::class);

            expect($untracked)->toContain('custom_field');
            expect($untracked)->toContain('id', 'created_at');
        });

        test('gets stageable attributes with model filter', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    stageableAttributes: ['title', 'content'],
                ),
            );

            $attributes = $registry->getStageableAttributes(Article::class, [
                'title' => 'Test',
                'content' => 'Content',
                'status' => 'draft',
            ]);

            expect($attributes)->toBe(['title' => 'Test', 'content' => 'Content']);
        });

        test('gets stageable attributes excluding unstageable', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    unstageableAttributes: ['admin_only'],
                ),
            );

            $attributes = $registry->getStageableAttributes(Article::class, [
                'title' => 'Test',
                'admin_only' => 'Secret',
            ]);

            expect($attributes)->toBe(['title' => 'Test']);
            expect($attributes)->not->toHaveKey('admin_only');
        });

        test('gets unstageable attributes with global defaults', function (): void {
            $registry = new ModelConfigurationRegistry();

            $unstageable = $registry->getUnstageableAttributes(Article::class);

            expect($unstageable)->toContain('id', 'created_at', 'updated_at', 'deleted_at');
        });

        test('gets revision diff strategy for model', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    revisionDiffStrategy: AttributeDiffStrategy::class,
                ),
            );

            $strategy = $registry->getRevisionDiffStrategy(Article::class);

            expect($strategy)->toBe(AttributeDiffStrategy::class);
        });

        test('gets null revision diff strategy for unconfigured model', function (): void {
            $registry = new ModelConfigurationRegistry();

            $strategy = $registry->getRevisionDiffStrategy(Article::class);

            expect($strategy)->toBeNull();
        });

        test('gets staged diff strategy for model', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    stagedDiffStrategy: SnapshotDiffStrategy::class,
                ),
            );

            $strategy = $registry->getStagedDiffStrategy(Article::class);

            expect($strategy)->toBe(SnapshotDiffStrategy::class);
        });

        test('gets approval strategy for model', function (): void {
            $registry = new ModelConfigurationRegistry();
            $registry->register(
                new ModelConfiguration(
                    Article::class,
                    approvalStrategy: QuorumApprovalStrategy::class,
                ),
            );

            $strategy = $registry->getApprovalStrategy(Article::class);

            expect($strategy)->toBe(QuorumApprovalStrategy::class);
        });
    });
});
