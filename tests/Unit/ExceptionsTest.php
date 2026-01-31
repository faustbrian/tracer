<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Exceptions\CannotApplyStagedChangeException;
use Cline\Tracer\Exceptions\CannotModifyStagedChangeException;
use Cline\Tracer\Exceptions\ConflictingMorphKeyMapsException;
use Cline\Tracer\Exceptions\InvalidStrategyClassException;
use Cline\Tracer\Exceptions\NonStringUlidPrimaryKeyException;
use Cline\Tracer\Exceptions\NonStringUuidPrimaryKeyException;
use Cline\Tracer\Exceptions\RevisionNotFoundByIdException;
use Cline\Tracer\Exceptions\RevisionNotFoundException;
use Cline\Tracer\Exceptions\RevisionNotFoundForModelException;
use Cline\Tracer\Exceptions\StagedChangeAlreadyTerminalException;
use Cline\Tracer\Exceptions\StagedChangeApplyFailedException;
use Cline\Tracer\Exceptions\StagedChangeNotApprovedException;
use Cline\Tracer\Exceptions\StagedChangeNotMutableException;
use Cline\Tracer\Exceptions\StagedChangeTargetNotFoundException;
use Cline\Tracer\Exceptions\UnknownApprovalStrategyException;
use Cline\Tracer\Exceptions\UnknownDiffStrategyException;
use Cline\Tracer\Tracer;

describe('Exceptions', function (): void {
    describe('CannotApplyStagedChangeException', function (): void {
        test('throws when applying unapproved change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();

            expect(fn () => Tracer::apply($staged, $user))
                ->toThrow(CannotApplyStagedChangeException::class);
        });

        test('throws when applying rejected change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::reject($staged, $user, 'Reason');

            expect(fn () => Tracer::apply($staged->fresh(), $user))
                ->toThrow(CannotApplyStagedChangeException::class);
        });

        test('notApproved includes status in message', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            $exception = StagedChangeNotApprovedException::forStagedChange($staged);

            expect($exception->getMessage())->toContain('pending');
            expect($exception->getMessage())->toContain('approved');
        });

        test('targetNotFound includes target info in message', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            $exception = StagedChangeTargetNotFoundException::forStagedChange($staged);

            expect($exception->getMessage())->toContain('target');
            expect($exception->getMessage())->toContain($staged->stageable_type);
        });

        test('applyFailed includes reason in message', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);

            $exception = StagedChangeApplyFailedException::forStagedChange($staged, 'Custom reason');

            expect($exception->getMessage())->toContain('Custom reason');
        });
    });

    describe('CannotModifyStagedChangeException', function (): void {
        test('throws when modifying approved change', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);

            expect(fn () => Tracer::updateProposedValues($staged->fresh(), ['title' => 'Different']))
                ->toThrow(CannotModifyStagedChangeException::class);
        });

        test('notMutable includes status in message', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);

            $exception = StagedChangeNotMutableException::forStagedChange($staged->fresh());

            expect($exception->getMessage())->toContain('approved');
            expect($exception->getMessage())->toContain('modified');
        });

        test('alreadyTerminal includes status in message', function (): void {
            $article = createArticle();
            $staged = Tracer::staging($article)->stage(['title' => 'New']);
            $user = createUser();
            Tracer::approve($staged, $user);
            Tracer::apply($staged, $user);

            $exception = StagedChangeAlreadyTerminalException::forStagedChange($staged->fresh());

            expect($exception->getMessage())->toContain('applied');
            expect($exception->getMessage())->toContain('cancelled');
        });
    });

    describe('RevisionNotFoundException', function (): void {
        test('throws when reverting to nonexistent version', function (): void {
            $article = createArticle();

            expect(fn () => Tracer::revisions($article)->revertTo(999))
                ->toThrow(RevisionNotFoundException::class);
        });

        test('forModel includes version in message', function (): void {
            $article = createArticle();

            $exception = RevisionNotFoundForModelException::forModel($article, 999);

            expect($exception->getMessage())->toContain('999');
        });

        test('forId includes id in message', function (): void {
            $exception = RevisionNotFoundByIdException::forId('some-id');

            expect($exception->getMessage())->toContain('some-id');
        });
    });

    describe('InvalidConfigurationException', function (): void {
        test('conflictingMorphKeyMaps creates descriptive exception', function (): void {
            $exception = ConflictingMorphKeyMapsException::create();

            expect($exception->getMessage())->toContain('morphKeyMap');
        });

        test('unknownDiffStrategy includes identifier in message', function (): void {
            $exception = UnknownDiffStrategyException::forIdentifier('invalid-strategy');

            expect($exception->getMessage())->toContain('invalid-strategy');
            expect($exception->getMessage())->toContain('diff');
        });

        test('unknownApprovalStrategy includes identifier in message', function (): void {
            $exception = UnknownApprovalStrategyException::forIdentifier('invalid-strategy');

            expect($exception->getMessage())->toContain('invalid-strategy');
            expect($exception->getMessage())->toContain('approval');
        });

        test('invalidStrategyClass includes class and interface in message', function (): void {
            $exception = InvalidStrategyClassException::forClass(
                'InvalidClass',
                'RequiredInterface',
            );

            expect($exception->getMessage())->toContain('InvalidClass');
            expect($exception->getMessage())->toContain('RequiredInterface');
        });
    });

    describe('NonStringUuidPrimaryKeyException', function (): void {
        test('forValue includes type in message', function (): void {
            $exception = NonStringUuidPrimaryKeyException::forValue(123);

            expect($exception->getMessage())->toContain('UUID');
            expect($exception->getMessage())->toContain('string');
            expect($exception->getMessage())->toContain('integer');
        });
    });

    describe('NonStringUlidPrimaryKeyException', function (): void {
        test('forValue includes type in message', function (): void {
            $exception = NonStringUlidPrimaryKeyException::forValue(456);

            expect($exception->getMessage())->toContain('ULID');
            expect($exception->getMessage())->toContain('string');
            expect($exception->getMessage())->toContain('integer');
        });
    });
});
