<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Media\MediaUploader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class MediaController extends Controller
{
    private const VIEW_FILTERS = ['all', 'recent', 'unused', 'missing-alt', 'pictime', 'wp'];

    private const PICKER_SORTS = ['newest', 'oldest', 'name', 'largest'];

    /** Mime-type groups the picker's type filter offers. */
    private const PICKER_TYPES = [
        'jpeg' => ['image/jpeg', 'image/jpg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    /** Accepted upload extensions, mirrored by the picker's file input. */
    private const UPLOAD_MIMES = 'jpeg,jpg,png,webp,gif';

    private const UPLOAD_MAX_KILOBYTES = 12288;

    public function __construct(private readonly MediaUploader $uploader) {}

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
        $perPage = (int) $request->integer('per_page', 60);
        $perPage = max(12, min(120, $perPage));

        $filter = $request->string('filter')->toString();

        if (! in_array($filter, self::VIEW_FILTERS, true)) {
            $filter = 'all';
        }

        $sort = $request->string('sort')->toString();

        if (! in_array($sort, self::PICKER_SORTS, true)) {
            $sort = 'newest';
        }

        $query = Media::query();

        $this->applyFilter($query, $filter);
        $this->applySearch($query, $request->string('q')->trim()->toString());
        $this->applyTypeFilter($query, $request->string('type')->toString());
        $this->applyPickerSort($query, $sort);

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (Media $media) => $this->pickerPayload($media))->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Ingest a batch of uploaded images straight into the library.
     *
     * Each file is validated on its own so one rejected photo — the odd HEIC
     * in a folder, a file over the size cap — doesn't throw away the rest of
     * the batch. The response reports both halves so the picker can surface
     * what landed and what didn't.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:60'],
            'files.*' => ['file'],
        ]);

        $uploaded = [];
        $failed = [];

        foreach ($request->file('files', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $name = $file->getClientOriginalName() ?: 'upload';

            $validator = Validator::make(['file' => $file], [
                'file' => ['required', 'file', 'image', 'mimes:'.self::UPLOAD_MIMES, 'max:'.self::UPLOAD_MAX_KILOBYTES],
            ], [], ['file' => $name]);

            if ($validator->fails()) {
                $failed[] = [
                    'filename' => $name,
                    'message' => (string) $validator->errors()->first('file'),
                ];

                continue;
            }

            $uploaded[] = $this->pickerPayload($this->uploader->store($file));
        }

        return response()->json([
            'data' => $uploaded,
            'failed' => $failed,
            'uploaded' => count($uploaded),
        ], $uploaded === [] && $failed !== [] ? 422 : 201);
    }

    /**
     * @return array{id:int,filename:string,alt_text:string|null,url:string|null,webp_url:string|null,width:int|null,height:int|null,mime_type:string|null,kind:string,created_at:string|null}
     */
    private function pickerPayload(Media $media): array
    {
        return [
            'id' => $media->id,
            'filename' => $media->filename,
            'alt_text' => $media->alt_text,
            'url' => $media->publicUrl(),
            'webp_url' => $media->webpPublicUrl(),
            'width' => $media->width,
            'height' => $media->height,
            'mime_type' => $media->mime_type,
            'kind' => $this->kindLabel($media),
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }

    /**
     * Short uppercase label shown on a picker tile ("JPEG", "WEBP", "IMAGE").
     */
    private function kindLabel(Media $media): string
    {
        $subtype = str_contains((string) $media->mime_type, '/')
            ? explode('/', (string) $media->mime_type)[1]
            : '';

        if ($subtype === '') {
            $subtype = pathinfo((string) $media->filename, PATHINFO_EXTENSION);
        }

        $subtype = strtoupper(trim($subtype));

        return match ($subtype) {
            '' => 'IMAGE',
            'JPG' => 'JPEG',
            default => $subtype,
        };
    }

    private function applyTypeFilter(Builder $query, string $type): void
    {
        $mimes = self::PICKER_TYPES[$type] ?? null;

        if ($mimes === null) {
            return;
        }

        $query->whereIn('mime_type', $mimes);
    }

    private function applyPickerSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('id'),
            'name' => $query->orderByRaw('LOWER(filename) asc')->orderBy('id'),
            // No byte size is stored, so "largest" ranks by pixel area.
            'largest' => $query->orderByRaw('(COALESCE(width, 0) * COALESCE(height, 0)) desc')->orderByDesc('id'),
            default => $query->latest('id'),
        };
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
            $this->uploader->applyFile($media, $request->file('file'));
        }

        $media->alt_text = $validated['alt_text'] ?? null;
        $media->caption = $validated['caption'] ?? null;
        $media->credit = $validated['credit'] ?? null;
        $media->focal_point_x = $validated['focal_point_x'] ?? null;
        $media->focal_point_y = $validated['focal_point_y'] ?? null;
        $media->save();

        if ($hasNewFile) {
            $this->uploader->optimize($media);
        }
    }
}
