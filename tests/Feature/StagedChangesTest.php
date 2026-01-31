<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Exceptions\CannotApplyStagedChangeException;
use Cline\Tracer\Exceptions\CannotModifyStagedChangeException;
use Cline\Tracer\Tracer;

describe('Staged Changes', function (): void {
    describe('staging changes', function (): void {
        test('can stage changes for later approval', function (): void {
            $article = createArticle('Original Title');

            $stagedChange = Tracer::staging($article)->stage([
                'title' => 'Proposed Title',
                'status' => 'pending_review',
            ], 'Need manager approval');

            expect($stagedChange)
                ->status->toBe(StagedChangeStatus::Pending)
                ->reason->toBe('Need manager approval')
                ->proposed_values->toMatchArray([
                    'title' => 'Proposed Title',
                    'status' => 'pending_review',
                ])
                ->original_values->toMatchArray([
                    'title' => 'Original Title',
                    'status' => 'draft',
                ]);

            // Model should NOT be changed yet
            expect($article->fresh()->title)->toBe('Original Title');
        });

        test('can stage multiple independent changes', function (): void {
            $article = createArticle();

            Tracer::staging($article)->stage(['title' => 'Change 1']);
            Tracer::staging($article)->stage(['content' => 'New content']);

            expect($article->stagedChanges()->count())->toBe(2);
            expect($article->pendingStagedChanges()->count())->toBe(2);
        });

        test('can update proposed values on pending change', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Initial Proposal']);

            Tracer::updateProposedValues($stagedChange, ['title' => 'Revised Proposal']);

            expect($stagedChange->fresh()->proposed_values['title'])->toBe('Revised Proposal');
        });
    });

    describe('approval workflow', function (): void {
        test('can approve a staged change', function (): void {
            $article = createArticle();
            $user = createUser('Approver');
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Approved Title']);

            $result = Tracer::approve($stagedChange, $user, 'Looks good');

            expect($result)->toBeTrue();
            expect($stagedChange->fresh()->status)->toBe(StagedChangeStatus::Approved);
        });

        test('can reject a staged change', function (): void {
            $article = createArticle();
            $user = createUser('Reviewer');
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Bad Title']);

            $result = Tracer::reject($stagedChange, $user, 'Not appropriate');

            expect($result)->toBeTrue();
            expect($stagedChange->fresh())
                ->status->toBe(StagedChangeStatus::Rejected)
                ->rejection_reason->toBe('Not appropriate');
        });

        test('tracks approval records', function (): void {
            $article = createArticle();
            $user = createUser('Approver');
            $stagedChange = Tracer::staging($article)->stage(['title' => 'New Title']);

            Tracer::approve($stagedChange, $user, 'Approved!');

            expect($stagedChange->approvals()->count())->toBe(1);
            expect($stagedChange->approvals()->first())
                ->approved->toBeTrue()
                ->comment->toBe('Approved!');
        });
    });

    describe('applying changes', function (): void {
        test('can apply an approved change', function (): void {
            $article = createArticle('Original Title');
            $stagedChange = Tracer::staging($article)->stage(['title' => 'New Title']);
            Tracer::approve($stagedChange);

            $result = Tracer::apply($stagedChange);

            expect($result)->toBeTrue();
            expect($stagedChange->fresh()->status)->toBe(StagedChangeStatus::Applied);
            expect($article->fresh()->title)->toBe('New Title');
        });

        test('cannot apply unapproved change', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Pending Title']);

            expect(fn () => Tracer::apply($stagedChange))
                ->toThrow(CannotApplyStagedChangeException::class);
        });

        test('cannot apply rejected change', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Rejected Title']);
            Tracer::reject($stagedChange, reason: 'No good');

            expect(fn () => Tracer::apply($stagedChange->fresh()))
                ->toThrow(CannotApplyStagedChangeException::class);
        });

        test('records applied_at timestamp', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'New Title']);
            Tracer::approve($stagedChange);
            Tracer::apply($stagedChange);

            expect($stagedChange->fresh()->applied_at)->not->toBeNull();
        });
    });

    describe('cancellation', function (): void {
        test('can cancel a pending change', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Cancelled']);

            Tracer::cancel($stagedChange);

            expect($stagedChange->status)->toBe(StagedChangeStatus::Cancelled);
        });

        test('cannot cancel an applied change', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'Applied']);
            Tracer::approve($stagedChange);
            Tracer::apply($stagedChange);

            expect(fn () => Tracer::cancel($stagedChange->fresh()))
                ->toThrow(CannotModifyStagedChangeException::class);
        });

        test('can cancel all pending changes for a model', function (): void {
            $article = createArticle();
            Tracer::staging($article)->stage(['title' => 'Change 1']);
            Tracer::staging($article)->stage(['content' => 'Change 2']);

            $cancelled = Tracer::staging($article)->cancelPending();

            expect($cancelled)->toBe(2);
            expect($article->pendingStagedChanges()->count())->toBe(0);
        });
    });

    describe('conductor API', function (): void {
        test('can use fluent staging conductor', function (): void {
            $article = createArticle();

            $conductor = Tracer::staging($article);
            $stagedChange = $conductor->stage(['title' => 'Via Conductor']);

            expect($stagedChange)->not->toBeNull();
            expect($conductor->pending()->count())->toBe(1);
            expect($conductor->hasPending())->toBeTrue();
        });

        test('can apply all approved changes via conductor', function (): void {
            $article = createArticle();
            $change1 = Tracer::staging($article)->stage(['title' => 'New Title']);
            $change2 = Tracer::staging($article)->stage(['status' => 'published']);

            Tracer::approve($change1);
            Tracer::approve($change2);

            $applied = Tracer::staging($article)->applyApproved();

            expect($applied)->toBe(2);
        });

        test('can get approval status via conductor', function (): void {
            $article = createArticle();
            $stagedChange = Tracer::staging($article)->stage(['title' => 'New']);

            $status = Tracer::staging($article)->approvalStatus($stagedChange);

            expect($status)
                ->toHaveKey('status')
                ->toHaveKey('approvals_required')
                ->toHaveKey('is_approved');
        });
    });

    describe('global queries', function (): void {
        test('can get all pending staged changes', function (): void {
            $article1 = createArticle('Article 1');
            $article2 = createArticle('Article 2');

            Tracer::staging($article1)->stage(['title' => 'Pending 1']);
            Tracer::staging($article2)->stage(['title' => 'Pending 2']);

            $pending = Tracer::allPendingStagedChanges();

            expect($pending->count())->toBe(2);
        });

        test('can get all approved staged changes', function (): void {
            $article = createArticle();
            $change1 = Tracer::staging($article)->stage(['title' => 'Change 1']);
            $change2 = Tracer::staging($article)->stage(['content' => 'Change 2']);

            Tracer::approve($change1);

            $approved = Tracer::allApprovedStagedChanges();

            expect($approved->count())->toBe(1);
        });
    });
});
