<?php

namespace App\Http\Controllers;

use App\Services\InventoryAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function show(Request $request, InventoryAccount $account): JsonResponse
    {
        return response()->json([
            'authenticated' => $account->authenticated($request),
            'configured' => $account->configured(),
            'csrf_token' => csrf_token(),
        ]);
    }

    public function store(Request $request, InventoryAccount $account): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        if (! $account->verify($data['username'], $data['password'])) {
            throw ValidationException::withMessages(['username' => 'Incorrect username or password.']);
        }
        $request->session()->regenerate();
        $request->session()->put('soloinventory_account', $account->fingerprint());

        return $this->show($request, $account);
    }

    public function destroy(Request $request, InventoryAccount $account): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->show($request, $account);
    }
}
