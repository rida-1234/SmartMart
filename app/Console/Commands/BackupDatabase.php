<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupDatabase extends Command
{
    protected $signature = 'smartmart:backup {--keep=30 : Days of backups to keep}';

    protected $description = 'Back up the shop database (safe while the app is running)';

    public function handle(): int
    {
        $dir = storage_path('backups');
        File::ensureDirectoryExists($dir);

        $file = $dir.DIRECTORY_SEPARATOR.'smartmart-'.now()->format('Y-m-d_His').'.sqlite';

        // VACUUM INTO writes a consistent copy even in WAL mode, unlike a raw file copy.
        DB::statement('VACUUM INTO ?', [$file]);

        // Second copy to a USB drive or a synced Google Drive folder, if configured.
        $extra = Setting::get('backup_path');
        if ($extra && is_dir($extra)) {
            File::copy($file, rtrim($extra, '\\/').DIRECTORY_SEPARATOR.basename($file));
            $this->line("Copied to {$extra}");
        } elseif ($extra) {
            $this->warn("Backup folder not found: {$extra} (is the USB drive plugged in?)");
        }

        $cutoff = now()->subDays((int) $this->option('keep'))->getTimestamp();
        foreach (File::files($dir) as $f) {
            if ($f->getExtension() === 'sqlite' && $f->getMTime() < $cutoff) {
                File::delete($f->getPathname());
            }
        }

        Setting::put('last_backup_at', now()->toDateTimeString());
        $this->info("Backup saved: {$file}");

        return self::SUCCESS;
    }
}
