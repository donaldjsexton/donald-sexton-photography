<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $page = Page::published()
            ->with(['heroMedia', 'blocks.media'])
            ->where('slug', 'about')
            ->first();

        return view('pages.show', [
            'page' => $page ?: Page::make([
                'title' => 'About',
                'excerpt' => 'I photograph weddings with a calm, simple approach so you can stay in the day and still come away with images that feel true to you.',
                'body' => <<<'HTML'
<p>I am Donald Sexton, a wedding photographer based in Clearwater and working across Tampa, the Gulf Coast, and beyond.</p>
<p>My goal is simple. I want your photos to feel honest, calm, and full of life. I pay attention to people first, then to light, timing, and the small moments that give the day its shape.</p>
<p>Some couples want big portraits. Some want gentle direction and room to breathe. Most want both. The work is built to hold all of that without making the day feel staged or heavy.</p>
<p>If you want photographs that feel easy to live with for years, the next step is to reach out and share the date, the place, and what matters most to you.</p>
HTML,
            ]),
            'eyebrow' => 'About',
        ]);
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }

    public function location(string $slug): View
    {
        $page = Page::published()
            ->with(['heroMedia', 'blocks.media'])
            ->where('template', 'location')
            ->where('slug', $slug)
            ->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'eyebrow' => 'Location',
        ]);
    }
}
