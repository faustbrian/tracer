<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Tracer\TracerServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as BaseTestCase;

use function env;

/**
 * Base test case for Tracer package tests.
 *
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Register the Tracer service provider for testing.
     *
     * @param  Application              $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TracerServiceProvider::class,
        ];
    }

    /**
     * Configure the test environment.
     *
     * @param Application $app
     */
    protected function defineEnvironment($app): void
    {
        $app->make(Repository::class)->set('tracer.primary_key_type', env('TRACER_PRIMARY_KEY_TYPE', 'id'));
        $app->make(Repository::class)->set('tracer.morph_type', env('TRACER_MORPH_TYPE', 'string'));

        // Disable morph map enforcement for tests
        Relation::morphMap([], merge: false);
        Relation::requireMorphMap(false);
    }

    /**
     * Create database tables required for testing.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
