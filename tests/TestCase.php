<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase\Tests;

use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Osmianski\FastRefreshDatabase\Tests\Fixtures\Harness;
use Osmianski\FastRefreshDatabase\FastRefreshDatabaseState;
use Osmianski\FastRefreshDatabase\FastRefreshDatabaseServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().'/fast-refresh-database-'.bin2hex(random_bytes(6));

        mkdir($this->workspace.'/migrations', 0777, true);
        mkdir($this->workspace.'/second', 0777, true);

        parent::setUp();

        FastRefreshDatabaseState::$cachedFingerprint = null;
        FastRefreshDatabaseState::$currentFingerprint = null;
        FastRefreshDatabaseState::$databases = null;
        FastRefreshDatabaseState::$attempted = false;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        exec('rm -rf '.escapeshellarg($this->workspace));
    }

    protected function getPackageProviders($app): array
    {
        return [FastRefreshDatabaseServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // File databases rather than `:memory:`, because what is being tested is
        // precisely what survives from one run to the next.
        $app['config']->set('database.default', 'primary');
        $app['config']->set('database.connections.primary', $this->sqlite('primary.sqlite'));
        $app['config']->set('database.connections.secondary', $this->sqlite('secondary.sqlite'));

        $app['config']->set('fast-refresh-database.databases', [
            'primary' => ['migrations' => [$this->workspace.'/migrations']],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function sqlite(string $file): array
    {
        touch($path = $this->workspace.'/'.$file);

        return [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];
    }

    protected function harness(): Harness
    {
        /** @var Application $app */
        $app = $this->app;

        return new Harness($app);
    }

    protected function writeMigration(string $directory, string $file, string $table, string $connection = 'primary'): void
    {
        file_put_contents($directory.'/'.$file, <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            protected \$connection = '{$connection}';

            public function up(): void
            {
                Schema::connection('{$connection}')->create('{$table}', function (Blueprint \$table) {
                    \$table->id();
                });
            }
        };
        PHP);
    }
}
