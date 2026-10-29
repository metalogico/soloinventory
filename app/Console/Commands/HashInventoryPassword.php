<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class HashInventoryPassword extends Command
{
    protected $signature = 'soloinventory:password-hash';

    protected $description = 'Generate a bcrypt hash for the private environment file';

    public function handle(): int
    {
        $password = $this->secret('Shared account password (at least 12 characters)');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Use at least 12 characters.');

            return self::FAILURE;
        }
        $this->line(Hash::make($password));

        return self::SUCCESS;
    }
}
