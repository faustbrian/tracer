<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Enums\StagedChangeStatus;
use Cline\Tracer\Strategies\Approval\QuorumApprovalStrategy;
use Cline\Tracer\Strategies\Approval\SimpleApprovalStrategy;
use Cline\Tracer\Strategies\Diff\AttributeDiffStrategy;
use Cline\Tracer\Strategies\Diff\SnapshotDiffStrategy;
use Cline\Tracer\Tracer;

describe('Strategies', function (): void {
    describe('SnapshotDiffStrategy', function (): void {
        test('calculates diff with old and new values', function (): void {
            $strategy = new SnapshotDiffStrategy();
            $article = createArticle();

            $diff = $strategy->calculate(
                ['title' => 'Old Title'],
                ['title' => 'New Title'],
                $article,
            );

            expect($diff)->toBe([
                'old' => ['title' => 'Old Title'],
                'new' => ['title' => 'New Title'],
            ]);
        });

        test('calculates diff with empty old values', function (): void {
            $strategy = new SnapshotDiffStrategy();
            $article = createArticle();

            $diff = $strategy->calculate(
                [],
                ['title' => 'New Title'],
                $article,
            );

            expect($diff)->toBe([
                'old' => [],
                'new' => ['title' => 'New Title'],
            ]);
        });

        test('applies diff in forward direction', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $result = $strategy->apply(
                ['title' => 'Current'],
                ['old' => ['title' => 'Old'], 'new' => ['title' => 'New']],
                false,
            );

            expect($result)->toBe(['title' => 'New']);
        });

        test('applies diff in reverse direction', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $result = $strategy->apply(
                ['title' => 'Current'],
                ['old' => ['title' => 'Old'], 'new' => ['title' => 'New']],
                true,
            );

            expect($result)->toBe(['title' => 'Old']);
        });

        test('describes changes', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $descriptions = $strategy->describe([
                'old' => ['title' => 'Old Title'],
                'new' => ['title' => 'New Title'],
            ]);

            expect($descriptions)->toHaveKey('title');
            expect($descriptions['title'])->toContain('Old Title');
            expect($descriptions['title'])->toContain('New Title');
        });

        test('describes new values when old is empty', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $descriptions = $strategy->describe([
                'old' => [],
                'new' => ['title' => 'New Title'],
            ]);

            expect($descriptions)->toHaveKey('title');
        });

        test('describes null values', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $descriptions = $strategy->describe([
                'old' => ['title' => null],
                'new' => ['title' => 'New Title'],
            ]);

            expect($descriptions)->toHaveKey('title');
        });

        test('describes boolean values', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $descriptions = $strategy->describe([
                'old' => ['active' => false],
                'new' => ['active' => true],
            ]);

            expect($descriptions)->toHaveKey('active');
        });

        test('describes array values', function (): void {
            $strategy = new SnapshotDiffStrategy();

            $descriptions = $strategy->describe([
                'old' => ['tags' => ['one']],
                'new' => ['tags' => ['one', 'two']],
            ]);

            expect($descriptions)->toHaveKey('tags');
        });

        test('returns identifier', function (): void {
            $strategy = new SnapshotDiffStrategy();

            expect($strategy->identifier())->toBe('snapshot');
        });
    });

    describe('AttributeDiffStrategy', function (): void {
        test('calculates diff with old and new values per attribute', function (): void {
            $strategy = new AttributeDiffStrategy();
            $article = createArticle();

            $diff = $strategy->calculate(
                ['title' => 'Old Title', 'count' => 5],
                ['title' => 'New Title', 'count' => 10],
                $article,
            );

            expect($diff)->toHaveKey('title');
            expect($diff)->toHaveKey('count');
            expect($diff['title'])->toBe(['old' => 'Old Title', 'new' => 'New Title']);
            expect($diff['count'])->toBe(['old' => 5, 'new' => 10]);
        });

        test('excludes unchanged attributes from diff', function (): void {
            $strategy = new AttributeDiffStrategy();
            $article = createArticle();

            $diff = $strategy->calculate(
                ['title' => 'Same', 'content' => 'Old Content'],
                ['title' => 'Same', 'content' => 'New Content'],
                $article,
            );

            expect($diff)->not->toHaveKey('title');
            expect($diff)->toHaveKey('content');
        });

        test('applies diff in forward direction', function (): void {
            $strategy = new AttributeDiffStrategy();

            $result = $strategy->apply(
                ['title' => 'Current'],
                ['title' => ['old' => 'Old', 'new' => 'New']],
                false,
            );

            expect($result)->toBe(['title' => 'New']);
        });

        test('applies diff in reverse direction', function (): void {
            $strategy = new AttributeDiffStrategy();

            $result = $strategy->apply(
                ['title' => 'Current'],
                ['title' => ['old' => 'Old', 'new' => 'New']],
                true,
            );

            expect($result)->toBe(['title' => 'Old']);
        });

        test('describes changes', function (): void {
            $strategy = new AttributeDiffStrategy();

            $descriptions = $strategy->describe([
                'title' => ['old' => 'Old Title', 'new' => 'New Title'],
            ]);

            expect($descriptions)->toHaveKey('title');
            expect($descriptions['title'])->toContain('Old Title');
            expect($descriptions['title'])->toContain('New Title');
        });

        test('describes setting a value from null', function (): void {
            $strategy = new AttributeDiffStrategy();

            $descriptions = $strategy->describe([
                'title' => ['old' => null, 'new' => 'New Title'],
            ]);

            expect($descriptions)->toHaveKey('title');
            expect($descriptions['title'])->toContain('Set to');
        });

        test('describes clearing a value to null', function (): void {
            $strategy = new AttributeDiffStrategy();

            $descriptions = $strategy->describe([
                'title' => ['old' => 'Old Title', 'new' => null],
            ]);

            expect($descriptions)->toHaveKey('title');
            expect($descriptions['title'])->toContain('Cleared');
        });

        test('returns identifier', function (): void {
            $strategy = new AttributeDiffStrategy();

            expect($strategy->identifier())->toBe('attribute');
        });
    });

    describe('SimpleApprovalStrategy', function (): void {
        test('returns identifier', function (): void {
            $strategy = resolve(SimpleApprovalStrategy::class);

            expect($strategy->identifier())->toBe('simple');
        });

        test('can approve pending staged change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(SimpleApprovalStrategy::class);

            expect($strategy->canApprove($staged))->toBeTrue();
        });

        test('cannot approve non-pending staged change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);
            $strategy = resolve(SimpleApprovalStrategy::class);

            expect($strategy->canApprove($staged->fresh()))->toBeFalse();
        });

        test('can reject pending staged change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(SimpleApprovalStrategy::class);

            expect($strategy->canReject($staged))->toBeTrue();
        });

        test('approves with single approval', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            $strategy = resolve(SimpleApprovalStrategy::class);

            $result = $strategy->approve($staged, $user, 'Looks good');

            expect($result)->toBeTrue();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Approved);
        });

        test('rejects with single rejection', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            $strategy = resolve(SimpleApprovalStrategy::class);

            $result = $strategy->reject($staged, $user, 'Not acceptable');

            expect($result)->toBeTrue();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Rejected);
        });

        test('returns status with approvals required', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(SimpleApprovalStrategy::class);

            $status = $strategy->status($staged);

            expect($status['strategy'])->toBe('simple');
            expect($status['approvals_required'])->toBe(1);
            expect($status['can_be_approved'])->toBeTrue();
        });
    });

    describe('QuorumApprovalStrategy', function (): void {
        test('returns identifier', function (): void {
            $strategy = resolve(QuorumApprovalStrategy::class);

            expect($strategy->identifier())->toBe('quorum');
        });

        test('can approve pending staged change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            expect($strategy->canApprove($staged))->toBeTrue();
        });

        test('cannot approve when already voted', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            $strategy = resolve(QuorumApprovalStrategy::class);

            $strategy->approve($staged, $user);

            expect($strategy->canApprove($staged->fresh(), $user))->toBeFalse();
        });

        test('requires multiple approvals for quorum', function (): void {
            config(['tracer.quorum.approvals_required' => 2]);
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            $user1 = createUser('User 1');
            $result1 = $strategy->approve($staged, $user1);

            expect($result1)->toBeFalse();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Pending);

            $user2 = createUser('User 2');
            $result2 = $strategy->approve($staged->fresh(), $user2);

            expect($result2)->toBeTrue();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Approved);
        });

        test('requires configured rejections to reject', function (): void {
            config(['tracer.quorum.rejections_required' => 2]);
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            $user1 = createUser('User 1');
            $result1 = $strategy->reject($staged, $user1, 'No');

            expect($result1)->toBeFalse();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Pending);

            $user2 = createUser('User 2');
            $result2 = $strategy->reject($staged->fresh(), $user2, 'No way');

            expect($result2)->toBeTrue();
            expect($staged->fresh()->status)->toBe(StagedChangeStatus::Rejected);
        });

        test('returns status with quorum details', function (): void {
            config(['tracer.quorum.approvals_required' => 3]);
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            $status = $strategy->status($staged);

            expect($status['strategy'])->toBe('quorum');
            expect($status['approvals_required'])->toBe(3);
            expect($status['approvals_received'])->toBe(0);
            expect($status['remaining_approvals'])->toBe(3);
        });

        test('uses config value for approvals', function (): void {
            config(['tracer.quorum.approvals_required' => 5]);
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            $status = $strategy->status($staged);

            expect($status['approvals_required'])->toBe(5);
        });

        test('uses config value for rejections', function (): void {
            config(['tracer.quorum.rejections_required' => 3]);
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $strategy = resolve(QuorumApprovalStrategy::class);

            $status = $strategy->status($staged);

            expect($status['rejections_required'])->toBe(3);
        });
    });
});
