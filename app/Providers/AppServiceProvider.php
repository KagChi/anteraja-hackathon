<?php

namespace App\Providers;

use App\Models\Package;
use App\Policies\PackagePolicy;
use App\Support\Appearance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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

        // Restore the theme the user picked in the mobile shells. Deferred to
        // booted() because native-ui loads the authored tokens from its own
        // provider's boot(); a pin applied any earlier would be overwritten by
        // that load. On the web console the stored mode is `system`, which is
        // a no-op.
        $this->app->booted(fn () => Appearance::apply());
    }
}
