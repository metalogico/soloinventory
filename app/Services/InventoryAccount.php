<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InventoryAccount
{
    public function configured(): bool
    {
        return config('soloinventory.username') !== ''
            && str_starts_with(config('soloinventory.password_hash'), '$2y$');
    }

    public function fingerprint(): string
    {
        $credentials = config('soloinventory.username').'|'.config('soloinventory.password_hash');

        return hash_hmac('sha256', $credentials, config('app.key'));
    }

    public function authenticated(Request $request): bool
    {
        return $this->configured()
            && hash_equals(
                $this->fingerprint(),
                (string) $request->session()->get('soloinventory_account', ''),
            );
    }

    public function verify(string $username, string $password): bool
    {
        return $this->configured()
            && hash_equals(config('soloinventory.username'), $username)
            && Hash::check($password, config('soloinventory.password_hash'));
    }
}
