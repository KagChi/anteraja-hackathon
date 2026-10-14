<?php

namespace App\Providers;

use App\Models\Package;
use App\Policies\PackagePolicy;
use App\Support\Appearance;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\System;

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

        RateLimiter::for('courier-auth', function (Request $request) {
            $email = (string) $request->input('email', '');
            $ip = $request->ip() ?? '127.0.0.1';

            return Limit::perMinute(5)->by($email.'|'.$ip)->response(function () {
                return response()->json([
                    'message' => 'Terlalu banyak percobaan login. Silakan coba lagi beberapa saat.',
                    'code' => 'TOO_MANY_REQUESTS',
                ], 429);
            });
        });

        // The landing page's resi lookup. Tracking numbers are sequential, so
        // the limit is what stops someone walking the whole range: generous for
        // a person typing a resi, tight for a scraper.
        RateLimiter::for('track-resi', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip() ?? '127.0.0.1')->response(function () {
                return response()->json([
                    'message' => 'Terlalu banyak pencarian resi. Coba lagi sebentar lagi.',
                    'code' => 'TOO_MANY_REQUESTS',
                ], 429);
            });
        });

        // Restore the theme the user picked in the mobile shells. Only they
        // carry that preference, and only they have a cache store to read it
        // from: an artisan run — composer's post-autoload package:discover,
        // the container build — must not go looking for one. Deferred to
        // booted() because native-ui loads the authored tokens from its own
        // provider's boot(); a pin applied any earlier would be overwritten by
        // that load.
        if (! config('nativephp-internal.running') && ! System::runningInJump()) {
            return;
        }

        $this->app->booted(fn () => Appearance::apply());
    }
}
