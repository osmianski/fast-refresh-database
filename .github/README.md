# FastRefreshDatabase for Laravel 🚀

`RefreshDatabase` runs `php artisan migrate:fresh` once per test run. On a suite with hundreds of migrations that is seconds of waiting before the first assertion — every run, including the one where you changed a single line of a test and nothing else.

Nothing about the database changed, so nothing needs migrating. This trait works out whether anything did, and when nothing did it goes straight to the transaction.

```php
use Osmianski\FastRefreshDatabase\FastRefreshDatabase;

pest()->extend(TestCase::class)
    ->use(FastRefreshDatabase::class)
    ->in('Feature');
```

Or in a `TestCase`:

```diff
-use Illuminate\Foundation\Testing\RefreshDatabase;
+use Osmianski\FastRefreshDatabase\FastRefreshDatabase;

 abstract class TestCase extends BaseTestCase
 {
-    use RefreshDatabase;
+    use FastRefreshDatabase;
 }
```

## Installation

```bash
composer require --dev osmianski/fast-refresh-database
```

A single-database application needs nothing else.

## What counts as a change

The trait keeps a fingerprint in `storage/app`, named for the databases it describes so tests running in parallel — each on its own database — don't read one another's. The databases are migrated again when:

- a migration file's **contents** changed, or one was added or removed;
- the seeder class's file, or anything under a configured `fingerprint` path, changed;
- the configuration itself changed — a path added, a seeder named;
- **the database no longer holds the migrations the fingerprint claims** — dropped by hand, in memory, or rolled back by a test that ran DDL.

That last one matters more than it sounds. A fingerprint records what a database *should* contain; only the database knows what it *does*. Without asking it, a suite runs happily against a schema that isn't there.

Modification times are deliberately not used: `git checkout` restamps files it didn't change, and re-migrating for that is the cost this package exists to avoid.

To force a migration, delete the fingerprint file.

## Several databases

Publish the config file when the tests need more than one database:

```bash
php artisan vendor:publish --tag=fast-refresh-database
```

```php
// config/fast-refresh-database.php
return [
    'databases' => [
        'primary' => [
            'seeder' => Database\Seeders\TestSeeder::class,
            'fingerprint' => [database_path('seeders')],
        ],
        'legacy' => [
            'migrations' => [base_path('../legacy/database/migrations')],
            'drop_views' => true,
        ],
    ],
];
```

Keys are connection names. Every option is optional: `migrations` defaults to the paths `php artisan migrate` would use on its own — `database/migrations` plus anything a package registered through `loadMigrationsFrom()` — `seeder` to none, `drop_views` and `drop_types` to false. A second database that only needs migrating is `'legacy' => []`.

The databases are wiped, migrated and seeded together, and the trait transacts on all of them, so a test writing to the second is rolled back like one writing to the first.

## Credit

This is a fork of [plannr/laravel-fast-refresh-database](https://github.com/PlannrCrm/laravel-fast-refresh-database) by [Sam Carré](https://github.com/Sammyjo20), whose idea this is. It exists because that package cannot be installed on Laravel 13 — it pins `symfony/process` to `^6.0 || ^7.0`, and the [one-line fix](https://github.com/PlannrCrm/laravel-fast-refresh-database/pull/21) has been open since May 2026 against a repository last pushed to in April 2024. The multi-database shape comes from a private codebase's own extension of the original.

The two cannot be installed side by side: this package declares a conflict with it, so Composer says so rather than leaving the autoloader to pick a winner.

MIT, as the original is.
