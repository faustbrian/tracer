<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer;

use Cline\Tracer\Configuration\ModelConfigurationRegistry;
use Cline\Tracer\Contracts\ApprovalStrategy;
use Cline\Tracer\Contracts\CauserResolver;
use Cline\Tracer\Contracts\DiffStrategy;
use Cline\Tracer\Database\Models\ModelRegistry;
use Cline\Tracer\Exceptions\ConflictingMorphKeyMapsException;
use Cline\Tracer\Resolvers\AuthCauserResolver;
use Cline\Tracer\Strategies\Approval\SimpleApprovalStrategy;
use Cline\Tracer\Strategies\Diff\SnapshotDiffStrategy;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function config;
use function is_array;

/**
 * Service provider for the Tracer package.
 *
 * Registers the Tracer manager, publishes configuration and migrations,
 * and sets up morph map configuration. Handles singleton bindings for
 * core services and default strategy implementations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TracerServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package settings.
     *
     * Defines the package name, configuration file, and database migrations
     * that will be published and loaded by the Laravel application.
     *
     * @param Package $package The package configuration builder
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('tracer')
            ->hasConfigFile()
            ->hasMigration('create_tracer_tables');
    }

    /**
     * Register the package's services in the container.
     *
     * Binds core Tracer services as singletons and registers default strategy
     * implementations. Strategy bindings can be overridden via configuration
     * to provide custom diff, approval, and causer resolution behavior.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        // TracerManager is registered as singleton via #[Singleton] attribute.
        // Explicit bindings are provided for better IDE support and testing.
        $this->app->singleton(TracerManager::class);

        // Configuration registry as singleton to maintain state across requests
        $this->app->singleton(ModelConfigurationRegistry::class);

        // ModelRegistry for morph map handling
        $this->app->singleton(ModelRegistry::class);

        // Default strategy bindings (can be overridden via config)
        $this->app->bind(function (): DiffStrategy {
            /** @var class-string<DiffStrategy> $strategy */
            $strategy = config('tracer.default_diff_strategy', SnapshotDiffStrategy::class);

            return $this->app->make($strategy);
        });

        $this->app->bind(function (): ApprovalStrategy {
            /** @var class-string<ApprovalStrategy> $strategy */
            $strategy = config('tracer.default_approval_strategy', SimpleApprovalStrategy::class);

            return $this->app->make($strategy);
        });

        $this->app->bind(function (): CauserResolver {
            /** @var class-string<CauserResolver> $resolver */
            $resolver = config('tracer.causer_resolver', AuthCauserResolver::class);

            return $this->app->make($resolver);
        });
    }

    /**
     * Bootstrap the package's services.
     *
     * Performs post-registration setup including morph map configuration
     * for polymorphic relationships used in revision and staging records.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->configureMorphKeyMaps();
    }

    /**
     * Configure Eloquent morph maps for polymorphic relationships.
     *
     * Applies either morphKeyMap or enforceMorphKeyMap configuration based on
     * which is defined in the tracer configuration. These maps ensure consistent
     * aliasing of model class names in polymorphic relationship columns.
     *
     * @throws ConflictingMorphKeyMapsException When both morphKeyMap and enforceMorphKeyMap are configured
     */
    private function configureMorphKeyMaps(): void
    {
        $morphKeyMap = config('tracer.morphKeyMap', []);
        $enforceMorphKeyMap = config('tracer.enforceMorphKeyMap', []);

        if (!is_array($morphKeyMap)) {
            $morphKeyMap = [];
        }

        if (!is_array($enforceMorphKeyMap)) {
            $enforceMorphKeyMap = [];
        }

        $hasMorphKeyMap = $morphKeyMap !== [];
        $hasEnforceMorphKeyMap = $enforceMorphKeyMap !== [];

        if ($hasMorphKeyMap && $hasEnforceMorphKeyMap) {
            throw ConflictingMorphKeyMapsException::create();
        }

        $registry = $this->app->make(ModelRegistry::class);

        if ($hasEnforceMorphKeyMap) {
            /** @var array<class-string, string> $enforceMorphKeyMap */
            $registry->enforceMorphKeyMap($enforceMorphKeyMap);
        } elseif ($hasMorphKeyMap) {
            /** @var array<class-string, string> $morphKeyMap */
            $registry->morphKeyMap($morphKeyMap);
        }
    }
}
