<?php

namespace App\Services;

use Illuminate\Database\Connectors\SQLiteConnector;
use PDO;

class UnicodeSQLiteConnector extends SQLiteConnector
{
    /**
     * Register Unicode search only when Laravel opens the actual connection.
     * Package discovery can resolve database services before a database exists.
     *
     * @param  array<string, mixed>  $config
     */
    public function connect(array $config): PDO
    {
        $connection = parent::connect($config);
        $connection->sqliteCreateFunction('unicode_lower', mb_strtolower(...), 1);

        return $connection;
    }
}
