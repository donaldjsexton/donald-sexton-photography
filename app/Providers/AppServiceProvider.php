<?php

namespace App\Providers;

use App\Models\SiteSetting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        if (View::exists('vendor.pagination.editorial')) {
            Paginator::defaultView('vendor.pagination.editorial');
        } else {
            Paginator::useTailwind();
        }

        View::composer(['layouts.app', 'layouts.admin'], function ($view): void {
            $siteSettings = SiteSetting::current();

            $view->with('siteSettings', $siteSettings);
            $view->with('analyticsMeasurementId', $siteSettings->analyticsMeasurementId());
        });
    }
}
