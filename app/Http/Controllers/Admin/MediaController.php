<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\MediaOptimizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const VIEW_FILTERS = ['all', 'recent', 'unused', 'missing-alt', 'pictime', 'wp'];

    public function __construct(private readonly MediaOptimizer $optimizer) {}

    public function index(Request $request): View
    {
        $filter = $request->string('filter')->toString();

        if (! in_array($filter, self::VIEW_FILTERS, true)) {
            $filter = 'all';
        }

        $term = $request->string('q')->trim()->toString();

        $query = Media::query()
            ->with([
                'weddingStories:id,title,slug',
                'journalPosts:id,title,slug',
                'pages:id,title,slug',
                'venues:id,name,slug',
            ]);

        $this->applyFilter($query, $filter);
        $this->applySearch($query, $term);

        $mediaItems = $query->latest()->paginate(48)->withQueryString();

        return view('admin.media.index', [
            'mediaItems' => $mediaItems,
            'stats' => $this->buildStats(),
            'recent' => Media::query()->latest()->take(8)->get(),
            'filter' => $filter,
            'search' => $term,
            'filters' => self::VIEW_FILTERS,
        ]);
    }

    /**
     * @return array{total:int,used:int,orphaned:int,missing_alt:int,this_month:int}
     */
    private function buildStats(): array
    {
        $total = Media::query()->count();
        $used = Media::query()->whereExists(fn ($sub) => $sub
            ->select(DB::raw(1))
            ->from('mediables')
            ->whereColumn('mediables.media_id', 'media.id')
        )->count();

        return [
            'total' => $total,
            'used' => $used,
            'orphaned' => max(0, $total - $used),
            'missing_alt' => Media::query()
                ->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))
                ->count(),
            'this_month' => Media::query()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'recent' => $query->where('created_at', '>=', now()->subDays(30)),
            'unused' => $query->whereNotExists(fn ($sub) => $sub
                ->select(DB::raw(1))
                ->from('mediables')
                ->whereColumn('mediables.media_id', 'media.id')
            ),
            'missing-alt' => $query->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', '')),
            'pictime' => $query->where('path', 'like', 'imports/pictime/%'),
            'wp' => $query->whereNotNull('original_wp_attachment_id'),
            default => null,
        };
    }

    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $needle = '%'.strtolower($term).'%';

        $query->where(function (Builder $builder) use ($term, $needle): void {
            if (ctype_digit($term)) {
                $builder->orWhere('id', (int) $term);
            }

            $builder
                ->orWhereRaw('LOWER(filename) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(alt_text) LIKE ?', [$needle]);
        });
    }

    public function picker(Request $request): JsonResponse
    {
        $term = $request->string('q')->trim()->toString();
        $perPage = (int) $request->integer('per_page', 60);
        $perPage = max(12, min(120, $perPage));

        $query = Media::query()->latest('id');

        if ($term !== '') {
            if (ctype_digit($term)) {
                $query->where(function ($builder) use ($term): void {
                    $builder
                        ->where('id', (int) $term)
                        ->orWhereRaw('LOWER(filename) LIKE ?', ['%'.strtolower($term).'%'])
                        ->orWhereRaw('LOWER(alt_text) LIKE ?', ['%'.strtolower($term).'%']);
                });
            } else {
                $needle = '%'.strtolower($term).'%';
                $query->where(function ($builder) use ($needle): void {
                    $builder
                        ->whereRaw('LOWER(filename) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(alt_text) LIKE ?', [$needle]);
                });
            }
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Media $media) => [
                'id' => $media->id,
                'filename' => $media->filename,
                'alt_text' => $media->alt_text,
                'url' => $media->publicUrl(),
                'webp_url' => $media->webpPublicUrl(),
            ])->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    public function create(): View
    {
        return view('admin.media.form', [
            'media' => new Media(['disk' => 'public']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMedia($request, true);

        $media = new Media;
        $this->fillMediaFromRequest($media, $request, $validated);

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('status', 'Media item created.');
    }

    public function edit(Media $media): View
    {
        return view('admin.media.form', [
            'media' => $media,
        ]);
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $validated = $this->validateMedia($request, false);

        $this->fillMediaFromRequest($media, $request, $validated);

        return redirect()
            ->route('admin.media.edit', $media)
            ->with('status', 'Media item updated.');
    }

    private function validateMedia(Request $request, bool $requireFile): array
    {
        return $request->validate([
            'file' => [$requireFile ? 'required' : 'nullable', 'file', 'image', 'max:12288'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'credit' => ['nullable', 'string', 'max:255'],
            'focal_point_x' => ['nullable', 'numeric', 'between:0,1'],
            'focal_point_y' => ['nullable', 'numeric', 'between:0,1'],
        ]);
    }

    private function fillMediaFromRequest(Media $media, Request $request, array $validated): void
    {
        $hasNewFile = $request->hasFile('file');

        if ($hasNewFile) {
            $file = $request->file('file');
            $path = $file->store('media/'.now()->format('Y/m'), 'public');
            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

            $media->disk = 'public';
            $media->path = $path;
            $media->filename = $file->getClientOriginalName();
            $media->mime_type = $file->getMimeType();
            $media->width = $width;
            $media->height = $height;
        }

        $media->alt_text = $validated['alt_text'] ?? null;
        $media->caption = $validated['caption'] ?? null;
        $media->credit = $validated['credit'] ?? null;
        $media->focal_point_x = $validated['focal_point_x'] ?? null;
        $media->focal_point_y = $validated['focal_point_y'] ?? null;
        $media->save();

        if ($hasNewFile) {
            try {
                $this->optimizer->optimizeUpload($media);
            } catch (\Throwable $throwable) {
                // Optimization is best-effort; the upload itself succeeded
                // and the original file is on disk. Log so an admin can
                // re-run media:optimize / media:generate-variants later.
                Log::warning('Media upload optimization failed.', [
                    'media_id' => $media->id,
                    'path' => $media->path,
                    'exception' => $throwable->getMessage(),
                ]);
            }
        }
    }
}
