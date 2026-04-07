<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Page;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View
    {
        $page = Page::published()->with('heroMedia')->where('slug', 'collections')->first();
        $collections = Collection::published()->orderBy('display_order')->get();

        return view('collections.index', [
            'page' => $page ?: Page::make([
                'title' => 'Collections',
                'excerpt' => 'Start with the coverage that fits your day, then add what matters most.',
            ]),
            'collections' => $collections,
        ]);
    }
}
