<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Contracts\Auth\Authenticatable;
use Tests\Fixtures\Article;
use Tests\Fixtures\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

/**
 * Set the currently authenticated user.
 */
function actingAs(Authenticatable $user): void
{
    test()->actingAs($user);
}

/**
 * Create a test user.
 */
function createUser(string $name = 'Test User', ?string $email = null): User
{
    return User::query()->create([
        'name' => $name,
        'email' => $email ?? str_replace(' ', '.', mb_strtolower($name)).'@example.com',
    ]);
}

/**
 * Create a test article.
 */
function createArticle(string $title = 'Test Article', string $content = 'Test content'): Article
{
    return Article::query()->create([
        'title' => $title,
        'content' => $content,
        'status' => 'draft',
    ]);
}
