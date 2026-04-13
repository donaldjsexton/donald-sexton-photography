<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\SiteSetting;
use App\Services\Gmail\GmailApiReader;
use App\Services\Gmail\GmailReader;
use App\Services\GoogleClient;
use Illuminate\Mail\MailManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GoogleClient::class, function () {
            return new GoogleClient(SiteSetting::current());
        });

        $this->app->bind(GmailReader::class, GmailApiReader::class);
    }

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

        /** @var MailManager $mail */
        $mail = $this->app['mail.manager'];

        $mail->extend('gmail', function () {
            return new GmailApiTransport($this->app->make(GoogleClient::class));
        });
    }
}
