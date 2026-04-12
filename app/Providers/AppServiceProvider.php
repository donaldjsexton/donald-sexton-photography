<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\SiteSetting;
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

        // Dynamically promote Gmail as the default mailer when Google is connected
        // and the gmail.send scope has been granted. Falls back to whatever MAIL_MAILER
        // is set in .env (Postmark in production, log in local).
        try {
            $settings = SiteSetting::current();

            if ($settings->googleIsConnected() && $settings->googleHasScope('https://www.googleapis.com/auth/gmail.send')) {
                config(['mail.default' => 'gmail']);
                config(['mail.mailers.gmail' => ['transport' => 'gmail']]);
                config(['mail.from.address' => $settings->google_connected_email]);
            }
        } catch (\Throwable) {
            // DB may not be available (e.g., during migrations). Safe to ignore.
        }
    }
}
