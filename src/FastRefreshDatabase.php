<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase;

use Throwable;
use SplFileInfo;
use ReflectionClass;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

trait FastRefreshDatabase
{
    use RefreshDatabase;

    /**
     * @throws \JsonException
     */
    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            if (! $this->testDatabasesAreCurrent()) {
                $this->migrateTestDatabases();
            }

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * @return array<string, TestDatabase>
     */
    protected function testDatabases(): array
    {
        return FastRefreshDatabaseState::$databases ??= $this->readTestDatabases();
    }

    /**
     * @return array<string, TestDatabase>
     */
    protected function readTestDatabases(): array
    {
        $configured = config('fast-refresh-database.databases') ?: [
            config('database.default') => [],
        ];

        $databases = [];

        foreach ($configured as $connection => $options) {
            $databases[$connection] = TestDatabase::fromConfig($connection, $options);
        }

        return $databases;
    }

    /**
     * Every connection the test transacts on, so a test writing to the second
     * database is rolled back like one writing to the first.
     *
     * @return list<string>
     */
    protected function connectionsToTransact(): array
    {
        return array_keys($this->testDatabases());
    }

    /**
     * @throws \JsonException
     */
    protected function testDatabasesAreCurrent(): bool
    {
        return $this->cachedFingerprint() === $this->currentFingerprint()
            && $this->testDatabasesHoldTheirMigrations();
    }

    /**
     * A fingerprint says what the databases should contain; this says whether
     * they still do. Without it, a database dropped by hand, an in-memory one,
     * or one a DDL test rolled back would all be taken on the fingerprint's
     * word and the suite would run against a schema that is not there.
     */
    protected function testDatabasesHoldTheirMigrations(): bool
    {
        try {
            foreach ($this->testDatabases() as $database) {
                $connection = $this->app['db']->connection($database->connection);
                $table = config('database.migrations.table', config('database.migrations', 'migrations'));

                if (! $connection->getSchemaBuilder()->hasTable($table)) {
                    return false;
                }

                if ($connection->table($table)->count() !== count($this->migrationFiles($database))) {
                    return false;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    protected function migrateTestDatabases(): void
    {
        // A failed migration fails every test either way; retrying it once per
        // test only buries the first error under a thousand identical ones.
        if (FastRefreshDatabaseState::$attempted) {
            return;
        }

        FastRefreshDatabaseState::$attempted = true;

        foreach ($this->testDatabases() as $database) {
            $this->artisan('db:wipe', [
                '--database' => $database->connection,
                '--drop-views' => $database->dropViews,
                '--drop-types' => $database->dropTypes,
            ]);

            $this->artisan('migrate', [
                '--database' => $database->connection,
                '--path' => $database->migrationPaths,
                '--realpath' => true,
            ]);

            if ($database->seeder) {
                $this->artisan('db:seed', [
                    '--database' => $database->connection,
                    '--class' => $database->seeder,
                ]);
            }
        }

        // We have just used the console kernel. For the sake of test purity,
        // let's pretend it didn't happen.
        $this->app[Kernel::class]->setArtisan(null);

        $this->updateLocalCacheOfInMemoryDatabases();

        file_put_contents($this->fingerprintPath(), $this->currentFingerprint());
    }

    protected function cachedFingerprint(): ?string
    {
        return FastRefreshDatabaseState::$cachedFingerprint ??= rescue(
            fn () => file_get_contents($this->fingerprintPath()),
            null,
            false,
        );
    }

    /**
     * Everything that, when it changes, should send the databases back through
     * `migrate` — the configuration itself included, so adding a path or a
     * seeder is picked up as readily as editing a migration.
     *
     * @throws \JsonException
     */
    protected function currentFingerprint(): string
    {
        if (FastRefreshDatabaseState::$currentFingerprint !== null) {
            return FastRefreshDatabaseState::$currentFingerprint;
        }

        $fingerprint = [];

        foreach ($this->testDatabases() as $connection => $database) {
            $fingerprint[$connection] = [
                'config' => $database,
                'migrations' => $this->fingerprintFiles($this->migrationFiles($database)),
                'seeder' => $database->seeder
                    ? $this->fingerprintFiles([new SplFileInfo($this->seederFile($database->seeder))])
                    : null,
                'fingerprint' => $this->fingerprintFiles($this->filesIn($database->fingerprintPaths, '*.*')),
            ];
        }

        return FastRefreshDatabaseState::$currentFingerprint = hash(
            'sha256',
            json_encode($fingerprint, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return list<SplFileInfo>
     */
    protected function migrationFiles(TestDatabase $database): array
    {
        return $this->filesIn($database->migrationPaths, '*.php');
    }

    /**
     * @param list<string> $paths
     * @return list<SplFileInfo>
     */
    protected function filesIn(array $paths, string $name): array
    {
        $paths = array_values(array_filter($paths, fn (string $path) => is_dir($path)));

        if ($paths === []) {
            return [];
        }

        $finder = Finder::create()
            ->in($paths)
            ->name($name)
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->files();

        return array_values(iterator_to_array($finder));
    }

    /**
     * Hashing contents rather than modification times: a `git checkout` restamps
     * files it did not change, and re-migrating for that is the slow path this
     * package exists to avoid.
     *
     * @param list<SplFileInfo> $files
     * @return array<string, string>
     */
    protected function fingerprintFiles(array $files): array
    {
        $fingerprints = [];

        foreach ($files as $file) {
            $fingerprints[$file->getPathname()] = (string) md5_file($file->getPathname());
        }

        ksort($fingerprints);

        return $fingerprints;
    }

    protected function seederFile(string $seeder): string
    {
        return (string) (new ReflectionClass($seeder))->getFileName();
    }

    /**
     * Named for the databases it describes, so tests running in parallel — each
     * on its own database — do not read one another's fingerprint.
     */
    protected function fingerprintPath(): string
    {
        $names = [];

        foreach ($this->testDatabases() as $database) {
            $names[] = (string) $this->app['db']->connection($database->connection)->getDatabaseName();
        }

        $names = implode('|', $names);

        return storage_path(sprintf(
            'app/fast-refresh-database_%s_%s.txt',
            Str::limit(Str::slug($names), 40, ''),
            substr(sha1($names), 0, 8),
        ));
    }
}
