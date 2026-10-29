<?php

namespace App\Http\Middleware;

use App\Services\InventoryAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInventoryAccount
{
    public function __construct(private InventoryAccount $account) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->account->authenticated($request), 401, 'Sign in to access the inventory.');

        return $next($request);
    }
}
