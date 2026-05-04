<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Redirect;
use App\Models\JournalPost;
use App\Models\Tag;
use App\Models\WeddingStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class JournalController extends Controller
{
    public function index(): View
    {
        return view('journal.index', [
            'title' => 'Journal',
            'description' => 'Wedding stories, venue notes, and easy planning help in one place.',
            'posts' => $this->basePostQuery()->paginate(10),
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return view('journal.index', [
            'title' => $category->name,
            'description' => $category->description,
            'posts' => $this->basePostQuery()
                ->whereHas('categories', fn ($query) => $query->whereKey($category->id))
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function tag(string $slug): View
    {
        $tag = Tag::query()->where('slug', $slug)->firstOrFail();

        return view('journal.index', [
            'title' => $tag->name,
            'description' => null,
            'posts' => $this->basePostQuery()
                ->whereHas('tags', fn ($query) => $query->whereKey($tag->id))
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        if (WeddingStory::published()->where('slug', $slug)->exists()) {
            return redirect()->route('weddings.show', $slug, 301);
        }

        $post = JournalPost::published()
            ->with(['heroMedia', 'categories', 'tags', 'venues', 'media'])
            ->where('slug', $slug)
            ->first();

        if ($post) {
            if ($weddingStory = $this->matchingWeddingStory($post)) {
                return redirect()->route('weddings.show', $weddingStory->slug, 301);
            }

            return view('journal.show', [
                'post' => $post,
                'relatedPosts' => JournalPost::relatedTo($post, 3),
            ]);
        }

        $redirect = Redirect::query()->whereIn('from_path', [
            '/journal/'.$slug,
            '/journal/'.$slug.'/',
        ])->first();

        if ($redirect) {
            return redirect()->to($redirect->to_path, $redirect->status_code);
        }
        throw new NotFoundHttpException();
    }

    private function basePostQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return JournalPost::published()
            ->whereNotIn('slug', WeddingStory::published()->select('slug'))
            ->where(function ($query) {
                $query->whereNull('canonical_url')
                    ->orWhere('canonical_url', '')
                    ->orWhereNotIn('canonical_url', WeddingStory::published()
                        ->whereNotNull('canonical_url')
                        ->where('canonical_url', '!=', '')
                        ->select('canonical_url'));
            })
            ->with(['heroMedia', 'categories', 'venues'])
            ->orderByRaw('CASE WHEN journal_posts.published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('journal_posts.published_at')
            ->orderByDesc('journal_posts.id');
    }

    private function matchingWeddingStory(JournalPost $post): ?WeddingStory
    {
        if (filled($post->canonical_url)) {
            $match = WeddingStory::published()
                ->where('canonical_url', $post->canonical_url)
                ->first();

            if ($match) {
                return $match;
            }
        }

        if (filled($post->original_wp_url)) {
            return WeddingStory::published()
                ->where(function ($query) use ($post) {
                    $query->where('canonical_url', $post->original_wp_url)
                        ->orWhere('original_wp_url', $post->original_wp_url);
                })
                ->first();
        }

        return null;
    }
}
