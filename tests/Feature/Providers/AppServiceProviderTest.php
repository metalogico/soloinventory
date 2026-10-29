<?php

use Illuminate\Database\SQLiteDatabaseDoesNotExistException;
use Illuminate\Support\Facades\DB;

it('can resolve a connection before the installation database exists', function () {
    $directory = sys_get_temp_dir().'/soloinventory-bootstrap-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $database = $directory.'/not-created.sqlite';
    config(['database.connections.bootstrap_check' => [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
    ]]);

    try {
        $connection = DB::connection('bootstrap_check');

        expect(file_exists($database))->toBeFalse();
        expect(fn () => $connection->getPdo())->toThrow(SQLiteDatabaseDoesNotExistException::class);
    } finally {
        DB::purge('bootstrap_check');
        if (file_exists($database)) {
            unlink($database);
        }
        rmdir($directory);
    }
});

it('keeps unicode search available after reconnecting to sqlite', function () {
    config(['database.connections.unicode_check' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]]);

    try {
        $first = DB::connection('unicode_check')->selectOne('SELECT unicode_lower(?) AS value', ['ÈCOLE']);
        DB::disconnect('unicode_check');
        $second = DB::connection('unicode_check')->selectOne('SELECT unicode_lower(?) AS value', ['MÜNCHEN']);

        expect($first->value)->toBe('ècole');
        expect($second->value)->toBe('münchen');
    } finally {
        DB::purge('unicode_check');
    }
});
