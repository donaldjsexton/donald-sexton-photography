<?php

namespace App\Providers;

use App\Mail\GmailApiTransport;
use App\Models\HomepageSetting;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\WeddingStory;
use App\Observers\IndexNowObserver;
use App\Services\Gmail\GmailApiReader;
use App\Services\Gmail\GmailReader;
use App\Services\GoogleClient;
use App\Support\HomeContent;
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

        $this->app->scoped(HomeContent::class, function () {
            return new HomeContent(HomepageSetting::query()->with('heroMedia')->first());
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

        JournalPost::observe(IndexNowObserver::class);
        WeddingStory::observe(IndexNowObserver::class);
        Page::observe(IndexNowObserver::class);
    }
}
