<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\JournalPost;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\WeddingStory;
use Illuminate\Database\Eloquent\Builder;
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
        if ($redirect = $this->redirectArchivedDuplicate($slug)) {
            return $redirect;
        }

        if (WeddingStory::published()->where('slug', $slug)->exists()) {
            return redirect()->route('weddings.show', $slug, 301);
        }

        $post = JournalPost::published()
            ->with(['heroMedia', 'categories', 'tags', 'venues', 'media', 'blocks.media', 'clientGallery'])
            ->where('slug', $slug)
            ->first();

        if ($post) {
            if ($weddingStory = $this->matchingWeddingStory($post)) {
                return redirect()->route('weddings.show', $weddingStory->slug, 301);
            }

            return view('journal.show', [
                'post' => $post,
                'relatedPosts' => JournalPost::relatedTo($post, 3),
                'relatedStories' => JournalPost::relatedStoriesTo($post, 3),
                'noindex' => $post->isArchivedSlugDuplicate(),
            ]);
        }

        $redirect = Redirect::query()->whereIn('from_path', [
            '/journal/'.$slug,
            '/journal/'.$slug.'/',
        ])->first();

        if ($redirect) {
            return redirect()->to($redirect->to_path, $redirect->status_code);
        }
        throw new NotFoundHttpException;
    }

    private function basePostQuery(): Builder
    {
        return JournalPost::published()
            ->withoutArchivedSlugDuplicates()
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
            ->recentFirst();
    }

    private function matchingWeddingStory(JournalPost $post): ?WeddingStory
    {
        return $post->supersedingWeddingStory();
    }

    /**
     * `-archived-{id}` slugs are leftover copies of a published post. Send them
     * to that post, or straight to the wedding story it already redirects to,
     * so the duplicate is not a second indexable URL.
     */
    private function redirectArchivedDuplicate(string $slug): ?RedirectResponse
    {
        if (preg_match('/^(?P<base>.+)-archived-\d+$/', $slug, $matches) !== 1) {
            return null;
        }

        $base = $matches['base'];

        if (WeddingStory::published()->where('slug', $base)->exists()) {
            return redirect()->route('weddings.show', $base, 301);
        }

        $canonicalPost = JournalPost::published()->where('slug', $base)->first();

        if ($canonicalPost) {
            if ($story = $canonicalPost->supersedingWeddingStory()) {
                return redirect()->route('weddings.show', $story->slug, 301);
            }

            return redirect()->route('journal.show', $canonicalPost->slug, 301);
        }

        $archivedPost = JournalPost::published()->where('slug', $slug)->first();

        if ($archivedPost && ($story = $archivedPost->supersedingWeddingStory())) {
            return redirect()->route('weddings.show', $story->slug, 301);
        }

        return null;
    }
}
