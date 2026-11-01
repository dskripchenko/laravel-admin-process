<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess;

use Dskripchenko\LaravelAdmin\Plugin\Concerns\RegistersAdminPlugin;
use Dskripchenko\LaravelAdminHealth\HealthRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Merges the config, loads the JSON translations and registers the plugin
 * in config('admin.plugins').
 */
final class AdminProcessServiceProvider extends ServiceProvider
{
    use RegistersAdminPlugin;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/admin-process.php', 'admin-process');

        // Registered here rather than in boot(): the translator caches a
        // locale's JSON lines on first use.
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');

        $this->registerAdminPlugin(AdminProcessPlugin::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/admin-process.php' => config_path('admin-process.php'),
        ], 'admin-process-config');

        // The engine's checks join laravel-admin-health when it is installed.
        if (class_exists(HealthRegistry::class) && config('admin-process.health.enabled', true)) {
            $this->callAfterResolving(HealthRegistry::class, function (HealthRegistry $registry): void {
                foreach (self::HEALTH_CHECKS as $class) {
                    $registry->register($class);
                }
            });
        }
    }

    /** @var list<class-string<Health\ProcessCheck>> */
    public const HEALTH_CHECKS = [
        Health\IncidentsCheck::class,
        Health\UnknownEffectsCheck::class,
        Health\TimersCheck::class,
        Health\QueuesCheck::class,
    ];
}
