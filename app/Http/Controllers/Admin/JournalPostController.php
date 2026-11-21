<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesPolymorphicMedia;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\JournalPost;
use App\Models\Media;
use App\Models\Tag;
use App\Models\Venue;
use App\Services\Cms\JournalPostManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JournalPostController extends Controller
{
    use ManagesPolymorphicMedia;

    public function __construct(private readonly JournalPostManager $posts) {}

    public function index(): View
    {
        return view('admin.journal-posts.index', [
            'posts' => JournalPost::query()->latest()->paginate(24),
        ]);
    }

    public function create(): View
    {
        return view('admin.journal-posts.form', $this->formData(new JournalPost([
            'status' => 'draft',
            'post_type' => 'advice',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->posts->rules());
        $post = new JournalPost;
        $this->posts->save($post, $validated);

        return redirect()
            ->route('admin.journal-posts.edit', $post)
            ->with('status', 'Journal post created.');
    }

    public function edit(JournalPost $journalPost): View
    {
        $journalPost->loadMissing(['heroMedia', 'media' => fn ($query) => $query->orderBy('mediables.sort_order'), 'allBlocks.media']);

        return view('admin.journal-posts.form', $this->formData($journalPost));
    }

    public function attachMedia(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->attachMediaToOwner($journalPost, $request);
    }

    public function detachMedia(JournalPost $journalPost, Media $media): JsonResponse
    {
        return $this->detachMediaFromOwner($journalPost, $media);
    }

    public function reorderMedia(JournalPost $journalPost, Request $request): JsonResponse
    {
        return $this->reorderOwnerMedia($journalPost, $request);
    }

    public function setHero(JournalPost $journalPost, Media $media): JsonResponse
    {
        return $this->promoteOwnerHero($journalPost, $media);
    }

    public function update(Request $request, JournalPost $journalPost): RedirectResponse
    {
        $validated = $request->validate($this->posts->rules($journalPost));
        $this->posts->save($journalPost, $validated);

        return redirect()
            ->route('admin.journal-posts.edit', $journalPost)
            ->with('status', 'Journal post updated.');
    }

    private function formData(JournalPost $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::query()->orderBy('name')->get(),
            'galleries' => Gallery::query()->orderBy('title')->get(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'venues' => Venue::query()->orderBy('name')->get(),
            'postTypes' => JournalPostManager::postTypes(),
            'statuses' => JournalPostManager::statuses(),
            'blockTypes' => array_filter(
                (array) config('blocks.types'),
                fn (array $definition): bool => ($definition['context'] ?? 'page') !== 'homepage',
            ),
        ];
    }
}
