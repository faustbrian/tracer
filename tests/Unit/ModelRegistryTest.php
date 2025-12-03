<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Database\Models\ModelRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\Fixtures\Article;

describe('ModelRegistry', function (): void {
    beforeEach(function (): void {
        // Clear any existing morph map before each test
        Relation::morphMap([], false);
    });

    test('registers morph key map', function (): void {
        $registry = new ModelRegistry();

        $registry->morphKeyMap([
            'article' => Article::class,
        ]);

        expect(Relation::morphMap())->toHaveKey('article');
        expect(Relation::morphMap()['article'])->toBe(Article::class);
    });

    test('merges with existing morph map', function (): void {
        Relation::morphMap(['existing' => 'App\Models\Existing']);
        $registry = new ModelRegistry();

        $registry->morphKeyMap([
            'article' => Article::class,
        ]);

        expect(Relation::morphMap())->toHaveKey('existing');
        expect(Relation::morphMap())->toHaveKey('article');
    });

    test('enforces morph key map', function (): void {
        $registry = new ModelRegistry();

        $registry->enforceMorphKeyMap([
            'article' => Article::class,
        ]);

        expect(Relation::morphMap())->toHaveKey('article');
        expect(Relation::morphMap()['article'])->toBe(Article::class);
    });
});
