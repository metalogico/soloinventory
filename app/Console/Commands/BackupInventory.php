<?php

namespace App\Console\Commands;

use App\Services\InventoryStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupInventory extends Command
{
    protected $signature = 'soloinventory:backup {--to= : Destination directory, outside photo storage}';

    protected $description = 'Create a consistent SQLite and photo backup under one lock';

    public function handle(InventoryStorage $storage): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('This command only supports SQLite.');

            return self::FAILURE;
        }
        $destination = $this->option('to') ?: storage_path('app/backups');
        File::ensureDirectoryExists($destination, 0700);
        $destination = realpath($destination);
        $storageRoot = Storage::disk('soloinventory')->path('');
        $photoRoot = realpath($storageRoot) ?: $storageRoot;

        if ($destination === $photoRoot || str_starts_with($destination.'/', rtrim($photoRoot, '/').'/')) {
            $this->error('The destination cannot be inside photo storage.');

            return self::FAILURE;
        }
        $snapshot = $destination.'/soloinventory-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));
        $pending = $snapshot.'.partial';

        try {
            $storage->synchronized(function () use ($pending, $snapshot): void {
                File::makeDirectory($pending, 0700);
                $this->copyDatabaseAndPhotos($pending);
                $this->writeManifest($pending);

                if (! rename($pending, $snapshot)) {
                    throw new RuntimeException('Backup finalization failed.');
                }
            });
        } catch (Throwable $exception) {
            File::deleteDirectory($pending);
            report($exception);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->info($snapshot);

        return self::SUCCESS;
    }

    private function copyDatabaseAndPhotos(string $directory): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('VACUUM INTO '.$pdo->quote($directory.'/database.sqlite'));

        $photos = Storage::disk('soloinventory')->path('photos');
        File::ensureDirectoryExists($directory.'/photos', 0700);

        if (is_dir($photos) && ! File::copyDirectory($photos, $directory.'/photos')) {
            throw new RuntimeException('Photo copy failed.');
        }
    }

    private function writeManifest(string $directory): void
    {
        $manifest = ['created_at' => now()->toIso8601String(), 'files' => []];

        foreach (File::allFiles($directory) as $file) {
            $manifest['files'][$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }

        File::put(
            $directory.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }
}
