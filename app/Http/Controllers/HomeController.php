<?php

namespace App\Http\Controllers;

use App\Support\HomeContent;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(HomeContent $content): View
    {
        $settings = $content->settings();

        return view('home.index', [
            'content' => $content,
            'settings' => $settings,
            'homeBlocks' => $settings?->blocks()->with('media')->get() ?? new Collection,
        ]);
    }
}
