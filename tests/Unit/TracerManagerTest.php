<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Configuration\ModelConfigurationBuilder;
use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Tracer;
use Cline\Tracer\TracerManager;
use Tests\Fixtures\Article;

describe('TracerManager', function (): void {
    test('gets revisions conductor for model', function (): void {
        $article = createArticle();

        $conductor = Tracer::revisions($article);

        expect($conductor)->not->toBeNull();
        expect($conductor->all())->toHaveCount(1);
    });

    test('gets staging conductor for model', function (): void {
        $article = createArticle();

        $conductor = Tracer::staging($article);

        expect($conductor)->not->toBeNull();
    });

    test('configures model and returns builder', function (): void {
        $builder = Tracer::configure(Article::class);

        expect($builder)->toBeInstanceOf(ModelConfigurationBuilder::class);
    });

    test('stages changes via facade', function (): void {
        $article = createArticle();

        $staged = Tracer::stage($article, ['title' => 'New Title'], 'Test reason');

        expect($staged)->toBeInstanceOf(StagedChange::class);
        expect($staged->reason)->toBe('Test reason');
        expect($staged->proposed_values['title'])->toBe('New Title');
    });

    test('approves staged change via facade', function (): void {
        $article = createArticle();
        $staged = Tracer::stage($article, ['title' => 'New']);
        $user = createUser();

        Tracer::approve($staged, $user, 'Approved');

        expect($staged->fresh()->status)->toBe(StagedChangeStatus::Approved);
    });

    test('rejects staged change via facade', function (): void {
        $article = createArticle();
        $staged = Tracer::stage($article, ['title' => 'New']);
        $user = createUser();

        Tracer::reject($staged, $user, 'Not acceptable');

        expect($staged->fresh()->status)->toBe(StagedChangeStatus::Rejected);
        expect($staged->fresh()->rejection_reason)->toBe('Not acceptable');
    });

    test('applies staged change via facade', function (): void {
        $article = createArticle('Original');
        $staged = Tracer::stage($article, ['title' => 'New Title']);
        $user = createUser();
        Tracer::approve($staged, $user);

        Tracer::apply($staged, $user);

        expect($staged->fresh()->status)->toBe(StagedChangeStatus::Applied);
        expect($article->fresh()->title)->toBe('New Title');
    });

    test('reverts to revision via facade', function (): void {
        $article = createArticle('Original');
        $article->update(['title' => 'Updated']);

        Tracer::revertTo($article, 1);

        expect($article->fresh()->title)->toBe('Original');
    });

    test('gets all pending staged changes', function (): void {
        $article1 = createArticle();
        $article2 = createArticle();

        Tracer::stage($article1, ['title' => 'New 1']);
        Tracer::stage($article2, ['title' => 'New 2']);

        $pending = Tracer::allPendingStagedChanges();

        expect($pending)->toHaveCount(2);
    });

    test('gets all approved staged changes', function (): void {
        $article = createArticle();
        $staged = Tracer::stage($article, ['title' => 'New']);
        $user = createUser();
        Tracer::approve($staged, $user);

        $approved = Tracer::allApprovedStagedChanges();

        expect($approved)->toHaveCount(1);
    });

    test('gets configuration registry', function (): void {
        $manager = resolve(TracerManager::class);

        $registry = $manager->getConfigurationRegistry();

        expect($registry)->not->toBeNull();
    });

    test('resolves diff strategy by identifier', function (): void {
        $strategy = Tracer::resolveDiffStrategy('snapshot');

        expect($strategy)->not->toBeNull();
        expect($strategy->identifier())->toBe('snapshot');
    });

    test('resolves attribute diff strategy by identifier', function (): void {
        $strategy = Tracer::resolveDiffStrategy('attribute');

        expect($strategy)->not->toBeNull();
        expect($strategy->identifier())->toBe('attribute');
    });

    test('resolves approval strategy by identifier', function (): void {
        $strategy = Tracer::resolveApprovalStrategy('simple');

        expect($strategy)->not->toBeNull();
        expect($strategy->identifier())->toBe('simple');
    });

    test('gets list of diff strategies', function (): void {
        $strategies = Tracer::getDiffStrategies();

        expect($strategies)->toContain('snapshot', 'attribute');
    });

    test('gets list of approval strategies', function (): void {
        $strategies = Tracer::getApprovalStrategies();

        expect($strategies)->toContain('simple', 'quorum');
    });
});
