<?php

namespace App\Providers;

use App\Http\Controllers\SitemapController;
use App\Mail\GmailApiTransport;
use App\Models\HomepageSetting;
use App\Models\Invoice;
use App\Models\JournalPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Observers\IndexNowObserver;
use App\Observers\InvoicePaymentObserver;
use App\Services\Gmail\GmailApiReader;
use App\Services\Gmail\GmailReader;
use App\Services\GoogleClient;
use App\Support\HomeContent;
use App\Tenancy\CurrentSite;
use App\Tenancy\DnsTxtDomainVerifier;
use App\Tenancy\DomainVerifier;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\MailManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentSite::class);

        $this->app->singleton(GoogleClient::class, function () {
            return new GoogleClient(SiteSetting::current());
        });

        $this->app->bind(GmailReader::class, GmailApiReader::class);

        $this->app->bind(DomainVerifier::class, DnsTxtDomainVerifier::class);

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
        Invoice::observe(InvoicePaymentObserver::class);

        $forgetSitemapCache = function (Model $model): void {
            SitemapController::forgetCache();
        };

        foreach ([JournalPost::class, WeddingStory::class, Page::class, Venue::class] as $sitemapModel) {
            $sitemapModel::saved($forgetSitemapCache);
            $sitemapModel::deleted($forgetSitemapCache);
        }

        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            return URL::route('portal.password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });
    }
}
