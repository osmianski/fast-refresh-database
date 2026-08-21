<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase\Tests\Fixtures;

use Illuminate\Foundation\Application;
use Illuminate\Contracts\Console\Kernel;
use Osmianski\FastRefreshDatabase\FastRefreshDatabase;
use Osmianski\FastRefreshDatabase\FastRefreshDatabaseState;

/**
 * The trait is written to be used by a test case, which is awkward to assert
 * about from inside a test case. This gives it the two things it actually
 * needs — an application and a way to run Artisan — and exposes the decisions
 * it makes.
 */
class Harness
{
    use FastRefreshDatabase;

    public function __construct(public Application $app)
    {
    }

    public function artisan(string $command, array $parameters = []): int
    {
        return $this->app[Kernel::class]->call($command, $parameters);
    }

    public function isCurrent(): bool
    {
        return $this->testDatabasesAreCurrent();
    }

    public function migrate(): void
    {
        FastRefreshDatabaseState::$attempted = false;

        $this->migrateTestDatabases();
    }

    /**
     * @return list<string>
     */
    public function connections(): array
    {
        return $this->connectionsToTransact();
    }

    public function fingerprintFile(): string
    {
        return $this->fingerprintPath();
    }
}
