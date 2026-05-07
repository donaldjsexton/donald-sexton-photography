<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BookedJobController as AdminBookedJobController;
use App\Http\Controllers\Admin\ClientController as AdminClientController;
use App\Http\Controllers\Admin\ConsoleCommandController as AdminConsoleCommandController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\GoogleOAuthController as AdminGoogleOAuthController;
use App\Http\Controllers\Admin\HomepageSettingsController as AdminHomepageSettingsController;
use App\Http\Controllers\Admin\ImportRunController as AdminImportRunController;
use App\Http\Controllers\Admin\InquiryController as AdminInquiryController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\JournalPostController as AdminJournalPostController;
use App\Http\Controllers\Admin\LogController as AdminLogController;
use App\Http\Controllers\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PicTimeImportController as AdminPicTimeImportController;
use App\Http\Controllers\Admin\PushSubscriptionController as AdminPushSubscriptionController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\Admin\WeddingStoryController as AdminWeddingStoryController;
use App\Http\Controllers\Admin\WordPressImportController as AdminWordPressImportController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InvoicePublicController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalFeedController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LlmsTxtController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\InvoiceController as PortalInvoiceController;
use App\Http\Controllers\Portal\PasswordResetController as PortalPasswordResetController;
use App\Http\Controllers\Portal\SquarePaymentController as PortalSquarePaymentController;
use App\Http\Controllers\QuestionnaireController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\Webhooks\SquareWebhookController;
use App\Http\Controllers\WeddingStoryController;
use App\Models\SiteSetting;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::get('/homepage', [AdminHomepageSettingsController::class, 'edit'])->name('homepage.edit');
        Route::put('/homepage', [AdminHomepageSettingsController::class, 'update'])->name('homepage.update');
        Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/google/connect', [AdminGoogleOAuthController::class, 'redirect'])->name('settings.google.connect');
        Route::get('/settings/google/callback', [AdminGoogleOAuthController::class, 'callback'])->name('settings.google.callback');
        Route::post('/settings/google/disconnect', [AdminGoogleOAuthController::class, 'disconnect'])->name('settings.google.disconnect');
        Route::post('/settings/google/business-profile', [AdminSettingsController::class, 'updateBusinessProfile'])->name('settings.gbp.update');
        Route::get('/inquiries', [AdminInquiryController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/create', [AdminInquiryController::class, 'create'])->name('inquiries.create');
        Route::post('/inquiries', [AdminInquiryController::class, 'store'])->name('inquiries.store');
        Route::post('/inquiries/{inquiry}/questionnaire', [AdminInquiryController::class, 'generateQuestionnaire'])->name('inquiries.questionnaire.generate');
        Route::get('/inquiries/{inquiry}/questionnaire', [AdminInquiryController::class, 'showQuestionnaire'])->name('inquiries.questionnaire.show');
        Route::get('/inquiries/{inquiry}/edit', [AdminInquiryController::class, 'edit'])->name('inquiries.edit');
        Route::put('/inquiries/{inquiry}', [AdminInquiryController::class, 'update'])->name('inquiries.update');
        Route::post('/inquiries/{inquiry}/reply', [AdminInquiryController::class, 'reply'])->name('inquiries.reply');
        Route::delete('/inquiries/{inquiry}', [AdminInquiryController::class, 'destroy'])->name('inquiries.destroy');

        Route::get('/booked-jobs', [AdminBookedJobController::class, 'index'])->name('booked-jobs.index');
        Route::get('/booked-jobs/{bookedJob}', [AdminBookedJobController::class, 'show'])->name('booked-jobs.show');
        Route::put('/booked-jobs/{bookedJob}', [AdminBookedJobController::class, 'update'])->name('booked-jobs.update');

        Route::get('/clients', [AdminClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [AdminClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [AdminClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [AdminClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [AdminClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [AdminClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [AdminClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('/inquiries/{inquiry}/convert-to-client', [AdminClientController::class, 'convertFromInquiry'])->name('clients.convert-from-inquiry');

        Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [AdminInvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/edit', [AdminInvoiceController::class, 'edit'])->name('invoices.edit');
        Route::put('/invoices/{invoice}', [AdminInvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/invoices/{invoice}', [AdminInvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('/invoices/{invoice}/send', [AdminInvoiceController::class, 'send'])->name('invoices.send');
        Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->name('invoices.void');
        Route::get('/invoices/{invoice}/pdf', [AdminInvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::post('/invoices/{invoice}/payments', [AdminInvoiceController::class, 'recordPayment'])->name('invoices.payments.store');

        Route::post('/push/subscribe', [AdminPushSubscriptionController::class, 'store'])->name('push.subscribe');
        Route::post('/push/unsubscribe', [AdminPushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

        Route::get('/media', [AdminMediaController::class, 'index'])->name('media.index');
        Route::get('/media/picker', [AdminMediaController::class, 'picker'])->name('media.picker');
        Route::get('/media/create', [AdminMediaController::class, 'create'])->name('media.create');
        Route::post('/media', [AdminMediaController::class, 'store'])->name('media.store');
        Route::get('/media/{media}/edit', [AdminMediaController::class, 'edit'])->name('media.edit');
        Route::put('/media/{media}', [AdminMediaController::class, 'update'])->name('media.update');

        Route::get('/pages', [AdminPageController::class, 'index'])->name('pages.index');
        Route::get('/pages/create', [AdminPageController::class, 'create'])->name('pages.create');
        Route::post('/pages', [AdminPageController::class, 'store'])->name('pages.store');
        Route::get('/pages/{page}/edit', [AdminPageController::class, 'edit'])->name('pages.edit');
        Route::put('/pages/{page}', [AdminPageController::class, 'update'])->name('pages.update');

        Route::get('/wedding-stories', [AdminWeddingStoryController::class, 'index'])->name('wedding-stories.index');
        Route::get('/wedding-stories/create', [AdminWeddingStoryController::class, 'create'])->name('wedding-stories.create');
        Route::post('/wedding-stories', [AdminWeddingStoryController::class, 'store'])->name('wedding-stories.store');
        Route::get('/wedding-stories/{weddingStory}/edit', [AdminWeddingStoryController::class, 'edit'])->name('wedding-stories.edit');
        Route::put('/wedding-stories/{weddingStory}', [AdminWeddingStoryController::class, 'update'])->name('wedding-stories.update');

        Route::get('/journal-posts', [AdminJournalPostController::class, 'index'])->name('journal-posts.index');
        Route::get('/journal-posts/create', [AdminJournalPostController::class, 'create'])->name('journal-posts.create');
        Route::post('/journal-posts', [AdminJournalPostController::class, 'store'])->name('journal-posts.store');
        Route::get('/journal-posts/{journalPost}/edit', [AdminJournalPostController::class, 'edit'])->name('journal-posts.edit');
        Route::put('/journal-posts/{journalPost}', [AdminJournalPostController::class, 'update'])->name('journal-posts.update');

        Route::get('/venues', [AdminVenueController::class, 'index'])->name('venues.index');
        Route::get('/venues/create', [AdminVenueController::class, 'create'])->name('venues.create');
        Route::post('/venues', [AdminVenueController::class, 'store'])->name('venues.store');
        Route::get('/venues/{venue}/edit', [AdminVenueController::class, 'edit'])->name('venues.edit');
        Route::put('/venues/{venue}', [AdminVenueController::class, 'update'])->name('venues.update');
        Route::delete('/venues/{venue}', [AdminVenueController::class, 'destroy'])->name('venues.destroy');

        Route::get('/console', [AdminConsoleCommandController::class, 'index'])->name('console.index');
        Route::post('/console/run', [AdminConsoleCommandController::class, 'run'])->name('console.run');

        Route::get('/logs', [AdminLogController::class, 'index'])->name('logs.index');

        Route::get('/imports', [AdminImportRunController::class, 'index'])->name('imports.index');
        Route::get('/imports/wordpress', [AdminWordPressImportController::class, 'index'])->name('imports.wordpress.index');
        Route::post('/imports/wordpress', [AdminWordPressImportController::class, 'store'])->name('imports.wordpress.store');
        Route::get('/imports/pictime', [AdminPicTimeImportController::class, 'index'])->name('imports.pictime.index');
        Route::post('/imports/pictime', [AdminPicTimeImportController::class, 'store'])->name('imports.pictime.store');
    });
});

Route::get('/', HomeController::class)->name('home');

Route::get('/about', [PageController::class, 'about'])->name('pages.about');
Route::get('/collections', [CollectionController::class, 'index'])->name('collections.index');

Route::get('/weddings', [WeddingStoryController::class, 'index'])->name('weddings.index');
Route::get('/weddings/{slug}', [WeddingStoryController::class, 'show'])->name('weddings.show');

Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
Route::get('/journal/feed.atom', JournalFeedController::class)->name('journal.feed');
Route::get('/journal/category/{slug}', [JournalController::class, 'category'])->name('journal.category');
Route::get('/journal/tag/{slug}', [JournalController::class, 'tag'])->name('journal.tag');
Route::get('/journal/{slug}', [JournalController::class, 'show'])->name('journal.show');

Route::get('/venues/search', [VenueController::class, 'search'])->name('venues.search');
Route::get('/venues', [VenueController::class, 'index'])->name('venues.index');
Route::get('/venues/{slug}', [VenueController::class, 'show'])->name('venues.show');
Route::get('/locations/{slug}', [PageController::class, 'location'])->name('pages.location');

Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms-of-service', [PageController::class, 'terms'])->name('legal.terms');

Route::get('/inquire', [InquiryController::class, 'create'])->name('inquiry.create');
Route::post('/inquire', [InquiryController::class, 'store'])->name('inquiry.store');
Route::get('/thank-you', [InquiryController::class, 'thankYou'])->name('inquiry.thank-you');

Route::get('/questionnaire/thank-you', [QuestionnaireController::class, 'thankYou'])->name('questionnaire.thank-you');
Route::get('/questionnaire/{questionnaire}', [QuestionnaireController::class, 'show'])->name('questionnaire.show');
Route::put('/questionnaire/{questionnaire}', [QuestionnaireController::class, 'update'])->name('questionnaire.update');

Route::middleware('signed')->group(function () {
    Route::get('/invoices/{invoice}', [InvoicePublicController::class, 'show'])->name('invoices.public.show');
    Route::get('/invoices/{invoice}/pdf', [InvoicePublicController::class, 'downloadPdf'])->name('invoices.public.pdf');
});

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:client')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'create'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'store'])->name('login.store');
        Route::get('/forgot-password', [PortalPasswordResetController::class, 'request'])->name('password.request');
        Route::post('/forgot-password', [PortalPasswordResetController::class, 'email'])->name('password.email');
        Route::get('/reset-password/{token}', [PortalPasswordResetController::class, 'reset'])->name('password.reset');
        Route::post('/reset-password', [PortalPasswordResetController::class, 'update'])->name('password.update');
    });

    Route::middleware('auth:client')->group(function () {
        Route::post('/logout', [PortalAuthController::class, 'destroy'])->name('logout');
        Route::get('/', PortalDashboardController::class)->name('dashboard');
        Route::get('/invoices', [PortalInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [PortalInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/pdf', [PortalInvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::post('/invoices/{invoice}/pay/square', [PortalSquarePaymentController::class, 'store'])->name('invoices.pay.square');
    });
});

Route::post('/webhooks/square', SquareWebhookController::class)
    ->name('webhooks.square')
    ->withoutMiddleware([ValidateCsrfToken::class]);

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/llms.txt', LlmsTxtController::class)->name('llms');

Route::get('/{key}.txt', function (string $key) {
    $expected = trim((string) (SiteSetting::current()->indexnow_key ?? ''));

    abort_if($expected === '' || ! hash_equals($expected, $key), 404);

    return response($expected."\n", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->where('key', '[a-f0-9]{8,128}')->name('indexnow.key');

Route::get('/robots.txt', function () {
    $aiCrawlers = [
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'ClaudeBot',
        'Claude-Web',
        'anthropic-ai',
        'PerplexityBot',
        'Perplexity-User',
        'Google-Extended',
        'Applebot-Extended',
        'Bytespider',
        'meta-externalagent',
        'Amazonbot',
        'CCBot',
    ];

    $lines = [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /admin/',
    ];

    foreach ($aiCrawlers as $agent) {
        $lines[] = '';
        $lines[] = 'User-agent: '.$agent;
        $lines[] = 'Allow: /';
        $lines[] = 'Disallow: /admin';
        $lines[] = 'Disallow: /admin/';
    }

    $lines[] = '';
    $lines[] = 'Sitemap: '.route('sitemap');

    return response(implode("\n", $lines)."\n", 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
})->name('robots');

Route::fallback(LegacyRedirectController::class);
