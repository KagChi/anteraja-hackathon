<?php

namespace App\Providers;

use App\Models\Package;
use App\Policies\PackagePolicy;
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
    }
}
