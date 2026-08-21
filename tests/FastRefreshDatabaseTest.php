<?php declare(strict_types=1);

namespace Osmianski\FastRefreshDatabase\Tests;

use Illuminate\Support\Facades\Schema;
use Osmianski\FastRefreshDatabase\FastRefreshDatabaseState;

class FastRefreshDatabaseTest extends TestCase
{
    public function test_it_migrates_when_nothing_has_been_migrated_yet(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');

        $harness = $this->harness();

        $this->assertFalse($harness->isCurrent());

        $harness->migrate();

        $this->assertTrue(Schema::connection('primary')->hasTable('widgets'));
        $this->assertFileExists($harness->fingerprintFile());
    }

    public function test_it_skips_migrating_when_nothing_has_changed(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');

        $this->harness()->migrate();

        $this->forgetWhatThisRunRemembers();

        $this->assertTrue($this->harness()->isCurrent());
    }

    public function test_it_migrates_again_when_a_migration_changes(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');

        $this->harness()->migrate();

        $this->forgetWhatThisRunRemembers();
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'gadgets');

        $this->assertFalse($this->harness()->isCurrent());
    }

    public function test_it_ignores_a_migration_that_was_only_restamped(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');

        $this->harness()->migrate();

        $this->forgetWhatThisRunRemembers();
        touch($this->workspace.'/migrations/2026_01_01_000000_create_widgets_table.php', time() + 3600);
        clearstatcache();

        $this->assertTrue($this->harness()->isCurrent());
    }

    public function test_it_migrates_again_when_the_database_no_longer_holds_its_migrations(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');

        $harness = $this->harness();
        $harness->migrate();

        $this->forgetWhatThisRunRemembers();

        Schema::connection('primary')->drop('widgets');
        Schema::connection('primary')->drop('migrations');

        // The fingerprint still says this database is migrated. The database says otherwise.
        $this->assertFileExists($harness->fingerprintFile());
        $this->assertFalse($this->harness()->isCurrent());
    }

    public function test_it_migrates_a_second_database_and_transacts_on_both(): void
    {
        $this->writeMigration($this->workspace.'/migrations', '2026_01_01_000000_create_widgets_table.php', 'widgets');
        $this->writeMigration($this->workspace.'/second', '2026_01_01_000000_create_gadgets_table.php', 'gadgets', 'secondary');

        config()->set('fast-refresh-database.databases', [
            'primary' => ['migrations' => [$this->workspace.'/migrations']],
            'secondary' => ['migrations' => [$this->workspace.'/second']],
        ]);

        FastRefreshDatabaseState::$databases = null;

        $harness = $this->harness();

        $this->assertSame(['primary', 'secondary'], $harness->connections());

        $harness->migrate();

        $this->assertTrue(Schema::connection('primary')->hasTable('widgets'));
        $this->assertTrue(Schema::connection('secondary')->hasTable('gadgets'));
        $this->assertTrue($harness->isCurrent());
    }

    public function test_it_uses_the_default_connection_when_no_database_is_configured(): void
    {
        config()->set('fast-refresh-database.databases', []);

        FastRefreshDatabaseState::$databases = null;

        $this->assertSame(['primary'], $this->harness()->connections());
    }

    /**
     * Everything a second run of the suite would have forgotten, so the next
     * assertion reads the fingerprint file and the database rather than what
     * this process already worked out.
     */
    protected function forgetWhatThisRunRemembers(): void
    {
        FastRefreshDatabaseState::$cachedFingerprint = null;
        FastRefreshDatabaseState::$currentFingerprint = null;
        FastRefreshDatabaseState::$attempted = false;
    }
}
