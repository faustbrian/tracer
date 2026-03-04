<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Database\Models\StagedChange;
use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Tracer;
use Tests\Fixtures\Article;

describe('Models', function (): void {
    describe('Revision', function (): void {
        test('belongs to traceable model', function (): void {
            $article = createArticle();
            $revision = $article->latestRevision();

            expect($revision->traceable)->toBeInstanceOf(Article::class);
            expect($revision->traceable->id)->toBe($article->id);
        });

        test('belongs to causer when authenticated', function (): void {
            $user = createUser();
            actingAs($user);

            $article = createArticle();
            $revision = $article->latestRevision();

            expect($revision->causer)->not->toBeNull();
            expect($revision->causer->id)->toBe($user->id);
        });

        test('has null causer when not authenticated', function (): void {
            $article = createArticle();
            $revision = $article->latestRevision();

            expect($revision->causer)->toBeNull();
        });

        test('checks if attribute changed', function (): void {
            $article = createArticle();
            $article->update(['title' => 'New Title']);

            $revision = $article->latestRevision();

            expect($revision->hasChangedAttribute('title'))->toBeTrue();
            expect($revision->hasChangedAttribute('content'))->toBeFalse();
        });

        test('gets old value for attribute', function (): void {
            $article = createArticle('Original Title');
            $article->update(['title' => 'New Title']);

            $revision = $article->latestRevision();

            expect($revision->getOldValue('title'))->toBe('Original Title');
            expect($revision->getOldValue('nonexistent'))->toBeNull();
            expect($revision->getOldValue('nonexistent', 'default'))->toBe('default');
        });

        test('gets new value for attribute', function (): void {
            $article = createArticle();
            $article->update(['title' => 'New Title']);

            $revision = $article->latestRevision();

            expect($revision->getNewValue('title'))->toBe('New Title');
            expect($revision->getNewValue('nonexistent'))->toBeNull();
            expect($revision->getNewValue('nonexistent', 'default'))->toBe('default');
        });
    });

    describe('StagedChange', function (): void {
        test('belongs to stageable model', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->stageable)->toBeInstanceOf(Article::class);
            expect($staged->stageable->id)->toBe($article->id);
        });

        test('belongs to author when authenticated', function (): void {
            $user = createUser();
            actingAs($user);

            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->author)->not->toBeNull();
            expect($staged->author->id)->toBe($user->id);
        });

        test('has approval relationship', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->approvals)->toBeEmpty();

            $user = createUser();
            Tracer::approve($staged, $user, 'Approved');

            expect($staged->fresh()->approvals)->toHaveCount(1);
        });

        test('stores proposed values as array', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New Title']);

            expect($staged->proposed_values['title'])->toBe('New Title');
            expect($staged->proposed_values['nonexistent'] ?? null)->toBeNull();
        });

        test('stores original values as array', function (): void {
            $article = createArticle('Original Title');
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->original_values['title'])->toBe('Original Title');
        });

        test('proposed values contain changed keys', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage([
                'title' => 'New Title',
                'content' => 'New Content',
            ]);

            $keys = array_keys($staged->proposed_values);

            expect($keys)->toContain('title', 'content');
        });

        test('proposed values indicate which attributes change', function (): void {
            $article = createArticle('Original');
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect(isset($staged->proposed_values['title']))->toBeTrue();
            expect(isset($staged->proposed_values['content']))->toBeFalse();
        });

        test('has pending status when created', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->status)->toBe(StagedChangeStatus::Pending);
            expect($staged->status->isMutable())->toBeTrue();
        });

        test('has approved status after approval', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);

            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Approved);
            expect($staged->fresh()->status->canBeApplied())->toBeTrue();
        });

        test('has rejected status after rejection', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::reject($staged, $user, 'Not good');

            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Rejected);
            expect($staged->fresh()->status->isTerminal())->toBeTrue();
        });

        test('has applied status after application', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);
            Tracer::apply($staged, $user);

            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Applied);
            expect($staged->fresh()->status->isTerminal())->toBeTrue();
        });

        test('is mutable only when pending', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            expect($staged->status->isMutable())->toBeTrue();

            $user = createUser();
            Tracer::approve($staged, $user);

            expect($staged->fresh()->status->isMutable())->toBeFalse();
        });
    });

    describe('StagedChangeApproval', function (): void {
        test('belongs to staged change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user, 'Looks good');

            $approval = $staged->fresh()->approvals->first();

            expect($approval->stagedChange)->toBeInstanceOf(StagedChange::class);
            expect($approval->stagedChange->id)->toBe($staged->id);
        });

        test('belongs to approver', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user, 'Looks good');

            $approval = $staged->fresh()->approvals->first();

            expect($approval->approver)->not->toBeNull();
            expect($approval->approver->id)->toBe($user->id);
        });

        test('records approval status', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user, 'Approved');

            $approval = $staged->fresh()->approvals->first();

            expect($approval->approved)->toBeTrue();
            expect($approval->comment)->toBe('Approved');
        });

        test('records rejection status', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::reject($staged, $user, 'Rejected');

            $approval = $staged->fresh()->approvals->first();

            expect($approval->approved)->toBeFalse();
            expect($approval->comment)->toBe('Rejected');
        });
    });
});
