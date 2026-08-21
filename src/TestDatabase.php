<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase;

use Illuminate\Support\Facades\App;

class TestDatabase
{
    /**
     * @param list<string> $migrationPaths
     * @param list<string> $fingerprintPaths
     */
    public function __construct(
        public string $connection,
        public array $migrationPaths,
        public ?string $seeder = null,
        public array $fingerprintPaths = [],
        public bool $dropViews = false,
        public bool $dropTypes = false,
    ) {
    }

    /**
     * @param array{migrations?: list<string>, seeder?: string|null, fingerprint?: list<string>, drop_views?: bool, drop_types?: bool} $options
     */
    public static function fromConfig(string $connection, array $options): self
    {
        return new self(
            connection: $connection,
            migrationPaths: self::absolute($options['migrations'] ?? self::registeredMigrationPaths()),
            seeder: $options['seeder'] ?? null,
            fingerprintPaths: self::absolute($options['fingerprint'] ?? []),
            dropViews: $options['drop_views'] ?? false,
            dropTypes: $options['drop_types'] ?? false,
        );
    }

    /**
     * Everything `php artisan migrate` would run on its own: the application's
     * own directory plus whatever packages registered through
     * `loadMigrationsFrom()`.
     *
     * @return list<string>
     */
    protected static function registeredMigrationPaths(): array
    {
        return array_values(array_unique(array_merge(
            [database_path('migrations')],
            App::make('migrator')->paths(),
        )));
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    protected static function absolute(array $paths): array
    {
        return array_values(array_map(
            fn (string $path) => str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path),
            $paths,
        ));
    }
}
