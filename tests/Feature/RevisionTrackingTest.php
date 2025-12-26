<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Enums\RevisionAction;
use Cline\Tracer\Tracer;

describe('Revision Tracking', function (): void {
    describe('automatic tracking', function (): void {
        test('creates a revision when a model is created', function (): void {
            $article = createArticle('My First Article', 'Some content');

            expect($article->revisions()->count())->toBe(1);

            $revision = $article->latestRevision();
            expect($revision)
                ->not->toBeNull()
                ->action->toBe(RevisionAction::Created)
                ->version->toBe(1)
                ->old_values->toBe([])
                ->new_values->toMatchArray([
                    'title' => 'My First Article',
                    'content' => 'Some content',
                    'status' => 'draft',
                ]);
        });

        test('creates a revision when a model is updated', function (): void {
            $article = createArticle();
            $article->update(['title' => 'Updated Title']);

            expect($article->revisions()->count())->toBe(2);

            $revision = $article->latestRevision();
            expect($revision)
                ->action->toBe(RevisionAction::Updated)
                ->version->toBe(2)
                ->old_values->toMatchArray(['title' => 'Test Article'])
                ->new_values->toMatchArray(['title' => 'Updated Title']);
        });

        test('creates a revision when a model is soft deleted', function (): void {
            $article = createArticle();
            $article->delete();

            $lastRevision = $article->revisions()->orderByDesc('version')->first();
            expect($lastRevision)
                ->action->toBe(RevisionAction::Deleted);
        });

        test('creates a revision when a model is restored', function (): void {
            $article = createArticle();
            $article->delete();
            $article->restore();

            $lastRevision = $article->revisions()->orderByDesc('version')->first();
            expect($lastRevision)
                ->action->toBe(RevisionAction::Restored);
        });

        test('does not create revision for unchanged attributes', function (): void {
            $article = createArticle();
            $initialCount = $article->revisions()->count();

            $article->update(['title' => $article->title]); // Same value

            expect($article->revisions()->count())->toBe($initialCount);
        });

        test('tracks multiple attribute changes in single revision', function (): void {
            $article = createArticle();
            $article->update([
                'title' => 'New Title',
                'content' => 'New Content',
                'status' => 'published',
            ]);

            $revision = $article->latestRevision();
            expect($revision->new_values)
                ->toHaveKeys(['title', 'content', 'status']);
        });
    });

    describe('version numbering', function (): void {
        test('increments version numbers sequentially', function (): void {
            $article = createArticle();
            expect($article->latestRevision()->version)->toBe(1);

            $article->update(['title' => 'V2']);
            expect($article->latestRevision()->version)->toBe(2);

            $article->update(['title' => 'V3']);
            expect($article->latestRevision()->version)->toBe(3);
        });

        test('can retrieve a specific version', function (): void {
            $article = createArticle('Original Title');
            $article->update(['title' => 'Second Title']);
            $article->update(['title' => 'Third Title']);

            $v1 = $article->getRevision(1);
            $v2 = $article->getRevision(2);

            expect($v1->new_values['title'])->toBe('Original Title');
            expect($v2->new_values['title'])->toBe('Second Title');
        });
    });

    describe('reverting', function (): void {
        test('can revert to a previous revision', function (): void {
            $article = createArticle('Original Title');
            $article->update(['title' => 'Updated Title']);

            Tracer::revisions($article)->revertTo(1);

            expect($article->fresh()->title)->toBe('Original Title');
        });

        test('creates a revert revision when reverting', function (): void {
            $article = createArticle('Original Title');
            $article->update(['title' => 'Updated Title']);
            Tracer::revisions($article)->revertTo(1);

            $latestRevision = $article->latestRevision();
            expect($latestRevision->action)->toBe(RevisionAction::Reverted);
            expect($latestRevision->metadata)->toHaveKey('reverted_to_version');
        });
    });

    describe('disabling tracking', function (): void {
        test('can disable tracking for specific operations', function (): void {
            $article = createArticle();
            $initialCount = $article->revisions()->count();

            Tracer::revisions($article)->withoutTracking(function () use ($article): void {
                $article->update(['title' => 'Silent Update']);
            });

            expect($article->revisions()->count())->toBe($initialCount);
            expect($article->fresh()->title)->toBe('Silent Update');
        });

        test('re-enables tracking after callback', function (): void {
            $article = createArticle();

            Tracer::revisions($article)->withoutTracking(fn () => $article->update(['title' => 'Silent']));
            $article->update(['title' => 'Tracked Update']);

            $latestRevision = $article->latestRevision();
            expect($latestRevision->new_values['title'])->toBe('Tracked Update');
        });
    });

    describe('conductor API', function (): void {
        test('can use fluent revision conductor', function (): void {
            $article = createArticle();
            $article->update(['title' => 'Updated']);

            $conductor = Tracer::revisions($article);

            expect($conductor->all()->count())->toBe(2);
            expect($conductor->latest()->version)->toBe(2);
            expect($conductor->count())->toBe(2);
        });

        test('can filter revisions by action', function (): void {
            $article = createArticle();
            $article->update(['title' => 'Updated']);
            $article->update(['title' => 'Updated Again']);

            $updates = Tracer::revisions($article)->byAction(RevisionAction::Updated);

            expect($updates->count())->toBe(2);
        });

        test('can get revisions for specific attribute', function (): void {
            $article = createArticle();
            $article->update(['title' => 'New Title']);
            $article->update(['content' => 'New Content']);

            $titleRevisions = Tracer::revisions($article)->forAttribute('title');

            expect($titleRevisions->count())->toBe(2); // Created + Updated
        });
    });
});
