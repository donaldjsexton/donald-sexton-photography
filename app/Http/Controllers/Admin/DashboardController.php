<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageSetting;
use App\Models\ImportRun;
use App\Models\Inquiry;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Page;
use App\Models\WeddingStory;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                ['label' => 'Media Items', 'value' => Media::query()->count()],
                ['label' => 'Pages', 'value' => Page::query()->count()],
                ['label' => 'Wedding Stories', 'value' => WeddingStory::query()->count()],
                ['label' => 'Journal Posts', 'value' => JournalPost::query()->count()],
                ['label' => 'Inquiries', 'value' => Inquiry::query()->count()],
                ['label' => 'Import Runs', 'value' => ImportRun::query()->count()],
            ],
            'homepageSetting' => HomepageSetting::query()->first(),
            'recentInquiries' => Inquiry::query()->latest()->limit(5)->get(),
            'recentImports' => ImportRun::query()->latest()->limit(5)->get(),
        ]);
    }
}
