<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase;

use Illuminate\Support\ServiceProvider;

class FastRefreshDatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'fast-refresh-database');
    }

    public function boot(): void
    {
        $this->publishes([$this->configPath() => config_path('fast-refresh-database.php')], 'fast-refresh-database');
    }

    protected function configPath(): string
    {
        return __DIR__.'/../config/fast-refresh-database.php';
    }
}
