<?php

namespace App\Providers;

use App\Services\UnicodeSQLiteConnector;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton('db.connector.sqlite', UnicodeSQLiteConnector::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('soloinventory.trusted_proxies') ?? []);
        Model::preventLazyLoading(! $this->app->isProduction());
        RateLimiter::for('soloinventory-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
