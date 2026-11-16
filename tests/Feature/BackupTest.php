<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/** Uses DatabaseMigrations (no wrapping transaction): VACUUM INTO can't run inside one. */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    public function test_backup_command_writes_a_file(): void
    {
        $this->artisan('smartmart:backup')->assertSuccessful();

        $files = glob(storage_path('backups/smartmart-*.sqlite'));
        $this->assertNotEmpty($files);
        array_map('unlink', $files);
    }
}
