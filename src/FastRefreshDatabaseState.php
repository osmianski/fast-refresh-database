<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase;

class FastRefreshDatabaseState
{
    /**
     * The fingerprint stored by the last run that migrated.
     */
    public static ?string $cachedFingerprint = null;

    /**
     * The fingerprint of what this run is about to test against.
     */
    public static ?string $currentFingerprint = null;

    /**
     * The databases named by the configuration, resolved once.
     *
     * @var array<string, TestDatabase>|null
     */
    public static ?array $databases = null;

    /**
     * Whether this run has already tried to migrate, successfully or not.
     */
    public static bool $attempted = false;
}
