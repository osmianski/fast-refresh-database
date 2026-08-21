<?php declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Test databases
    |--------------------------------------------------------------------------
    |
    | Every database the tests need, keyed by connection name. Each is wiped,
    | migrated and seeded together, and the trait transacts on all of them.
    |
    | Leave this empty and the default connection is used, migrated from the
    | paths `php artisan migrate` would use on its own — which is every
    | single-database application.
    |
    | Each key is optional:
    |
    |     'mysql' => [
    |         'migrations' => [database_path('migrations')],
    |         'seeder' => Database\Seeders\TestSeeder::class,
    |         'fingerprint' => [database_path('seeders')],
    |         'drop_views' => false,
    |         'drop_types' => false,
    |     ],
    |
    | `fingerprint` names files and directories that should force a re-migration
    | when they change — a schema dump, the seeders a seeder class calls into.
    | The seeder class's own file is fingerprinted already.
    |
    */

    'databases' => [
        //
    ],

];
