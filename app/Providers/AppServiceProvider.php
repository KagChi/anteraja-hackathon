<?php

namespace App\Providers;

use App\Models\Package;
use App\Policies\PackagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
        Gate::policy(Package::class, PackagePolicy::class);

        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        RateLimiter::for('client-auth', function (Request $request) {
            $phone = (string) $request->input('phone', '');
            $ip = $request->ip() ?? '127.0.0.1';

            return Limit::perMinute(5)->by($phone.'|'.$ip)->response(function () {
                return response()->json([
                    'message' => 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat.',
                    'code' => 'TOO_MANY_REQUESTS',
                ], 429);
            });
        });
    }
}
