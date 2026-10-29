<?php

use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('backup restores a coherent database and photo collection with verifiable hashes', function () {
    $directory = storage_path('framework/testing/backup-'.Str::uuid());
    File::ensureDirectoryExists($directory);
    touch($directory.'/source.sqlite');
    config(['database.connections.snapshot' => [...config('database.connections.sqlite'), 'database' => $directory.'/source.sqlite'], 'database.default' => 'snapshot']);
    Storage::fake('soloinventory');
    try {
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $item = InventoryItem::factory()->create(['name' => 'Da recuperare', 'serial' => '0007', 'photo_path' => 'photos/saved.jpg']);
        Storage::disk('soloinventory')->put('photos/saved.jpg', 'photograph bytes');

        $this->artisan('soloinventory:backup', ['--to' => $directory.'/backups'])->assertSuccessful();
        $snapshot = File::directories($directory.'/backups')[0];
        $manifest = json_decode(File::get($snapshot.'/manifest.json'), true);
        foreach ($manifest['files'] as $path => $hash) {
            expect(hash_file('sha256', $snapshot.'/'.$path))->toBe($hash);
        }
        config(['database.connections.recovered' => [...config('database.connections.sqlite'), 'database' => $snapshot.'/database.sqlite']]);
        expect(DB::connection('recovered')->table('inventory_items')->where('id', $item->id)->value('serial'))->toBe('0007');
        expect(File::get($snapshot.'/photos/saved.jpg'))->toBe('photograph bytes');
        expect(DB::connection('recovered')->selectOne('PRAGMA integrity_check')->integrity_check)->toBe('ok');
        expect(glob($directory.'/backups/*.partial'))->toBe([]);
    } finally {
        DB::purge('snapshot');
        DB::purge('recovered');
        config(['database.default' => 'sqlite']);
        File::deleteDirectory($directory);
    }
});

test('backup refuses a destination inside the photo archive', function () {
    Storage::fake('soloinventory');
    $this->artisan('soloinventory:backup', ['--to' => Storage::disk('soloinventory')->path('nested')])->assertFailed();
});
