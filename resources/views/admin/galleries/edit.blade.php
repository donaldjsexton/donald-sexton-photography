@extends('layouts.admin-v2')

@section('title', 'Edit Gallery')
@section('eyebrow', 'Content')
@section('heading', $gallery->title)
@section('subheading', 'Manage albums, photos, and share links for this gallery.')
@section('header_actions')
    <x-ui.button variant="secondary" :href="route('admin.galleries.index')">Back to galleries</x-ui.button>
@endsection
@section('content')
    @php
        $input = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100';
    @endphp
    @if ($errors->any())
        <ul class="mb-4 list-disc rounded-xl border border-rose-200 bg-rose-50 px-8 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Gallery settings --}}
    <x-ui.card title="Settings">
        <form method="POST" action="{{ route('admin.galleries.update', $gallery) }}" class="admin-form max-w-none! space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field label="Title">
                    <input class="{{ $input }}" type="text" name="title" value="{{ old('title', $gallery->title) }}" required>
                </x-ui.field>
                <x-ui.field label="Visibility">
                    <select class="{{ $input }}" name="visibility">
                        <option value="private" @selected($gallery->visibility === 'private')>Private (link only)</option>
                        <option value="public" @selected($gallery->visibility === 'public')>Public</option>
                    </select>
                </x-ui.field>
                <x-ui.field label="Client (optional)">
                    <select class="{{ $input }}" name="client_id">
                        <option value="">Unassigned</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected($gallery->client_id === $client->id)>{{ $client->displayName() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field :label="($gallery->password ? 'Change password' : 'Set password').' (optional)'">
                    <input class="{{ $input }}" type="text" name="password" value="" autocomplete="off" placeholder="Leave blank to keep current">
                </x-ui.field>
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="requires_payment" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($gallery->requires_payment)>
                Require the balance to be paid before full-resolution downloads
            </label>
            @if ($gallery->password)
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="remove_password" value="1" class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"> Remove password protection
                </label>
            @endif
            <div>
                <x-ui.button type="submit">Save settings</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    {{-- Albums + photos --}}
    <x-ui.card title="Albums" class="mt-6">
        <form method="POST" action="{{ route('admin.galleries.albums.store', $gallery) }}" class="max-w-none! flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <x-ui.field label="Album name" class="flex-1">
                <input class="{{ $input }}" type="text" name="name" placeholder="e.g. Ceremony" required>
            </x-ui.field>
            <input type="hidden" name="visibility" value="private">
            <x-ui.button type="submit">Add album</x-ui.button>
        </form>

        <div class="mt-4 space-y-6"
             data-gallery-uploads
             data-gallery-channel="galleries.{{ $gallery->id }}"
             data-gallery-event=".upload.progressed"
             data-max-request-bytes="{{ $maxUploadRequestBytes }}">
        @forelse ($gallery->albums as $album)
            <div class="album-block space-y-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <form method="POST" action="{{ route('admin.galleries.albums.update', [$gallery, $album]) }}" class="max-w-none! flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        @method('PUT')
                        <x-ui.field label="Name">
                            <input class="{{ $input }}" type="text" name="name" value="{{ $album->name }}" required>
                        </x-ui.field>
                        <select class="{{ $input }} sm:w-auto" name="visibility" aria-label="Album visibility">
                            <option value="private" @selected($album->visibility === 'private')>Private</option>
                            <option value="public" @selected($album->visibility === 'public')>Public</option>
                        </select>
                        <x-ui.button variant="secondary" type="submit">Rename</x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('admin.galleries.albums.destroy', [$gallery, $album]) }}"
                          class="max-w-none!" onsubmit="return confirm('Delete this album and its photos?');">
                        @csrf
                        @method('DELETE')
                        <x-ui.button variant="danger" type="submit">Delete album</x-ui.button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.galleries.albums.photos.store', [$gallery, $album]) }}"
                      enctype="multipart/form-data" class="admin-form max-w-none! space-y-3"
                      data-upload-form
                      data-album-id="{{ $album->id }}"
                      data-upload-url="{{ route('admin.galleries.albums.photos.upload', [$gallery, $album]) }}">
                    @csrf
                    <x-ui.field label="Upload photos (JPEG, PNG, or WebP — select as many as you like)">
                        <input class="{{ $input }}" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    </x-ui.field>
                    <p class="upload-dropzone" data-upload-dropzone>Drop photos here to upload them to this album</p>
                    <div>
                        <x-ui.button type="submit">Upload</x-ui.button>
                    </div>
                </form>

                <p class="upload-status" data-upload-status data-album-id="{{ $album->id }}" hidden></p>

                @if ($album->photos->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400" data-empty-note>No photos in this album yet.</p>
                @endif
                <div class="gallery-photo-grid" data-photo-grid data-album-id="{{ $album->id }}">
                    @foreach ($album->photos as $photo)
                        <div class="gallery-photo" data-photo-id="{{ $photo->id }}">
                            <img src="{{ route('admin.galleries.photos.thumb', [$gallery, $photo]) }}" alt="{{ $photo->original_name }}" loading="lazy">
                            <div class="gallery-photo__bar">
                                <form method="POST" action="{{ route('admin.galleries.cover', [$gallery, $photo]) }}" class="max-w-none! flex-1">
                                    @csrf
                                    <x-ui.button variant="secondary" type="submit" class="w-full !px-2 !py-1.5 text-xs disabled:opacity-60"
                                            :disabled="$gallery->cover_photo_id === $photo->id">
                                        {{ $gallery->cover_photo_id === $photo->id ? 'Cover' : 'Set cover' }}
                                    </x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.galleries.albums.photos.destroy', [$gallery, $album, $photo]) }}" class="max-w-none! flex-1"
                                      onsubmit="return confirm('Remove this photo?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button variant="danger" type="submit" class="w-full !px-2 !py-1.5 text-xs">Remove</x-ui.button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Add an album to start uploading photos.</p>
        @endforelse
        </div>
    </x-ui.card>

    {{-- Share links --}}
    <x-ui.card title="Share links" class="mt-6">
        <form method="POST" action="{{ route('admin.galleries.shares.store', $gallery) }}" class="admin-form max-w-none! space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-ui.field label="Scope">
                    <select class="{{ $input }}" name="album_id">
                        <option value="">Whole gallery</option>
                        @foreach ($gallery->albums as $album)
                            <option value="{{ $album->id }}">{{ $album->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Expires (optional)"><input class="{{ $input }}" type="datetime-local" name="expires_at"></x-ui.field>
                <x-ui.field label="Password (optional)"><input class="{{ $input }}" type="text" name="password" autocomplete="off"></x-ui.field>
            </div>
            <div>
                <x-ui.button type="submit">Create share link</x-ui.button>
            </div>
        </form>

        <div class="mt-4">
        @forelse ($gallery->shareTokens as $shareToken)
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                <div class="min-w-0 text-sm">
                    <strong class="text-gray-900 dark:text-white">{{ $shareToken->shareable_type === \App\Models\Album::class ? ($shareToken->shareable->name ?? 'Album') : 'Whole gallery' }}</strong>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        <code class="break-all">{{ route('galleries.share.show', $shareToken->token) }}</code>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $shareToken->password ? 'Password protected · ' : '' }}{{ $shareToken->expires_at ? 'Expires '.$shareToken->expires_at->format('M j, Y H:i') : 'No expiry' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.galleries.shares.destroy', [$gallery, $shareToken]) }}" class="max-w-none!"
                      onsubmit="return confirm('Revoke this share link?');">
                    @csrf
                    @method('DELETE')
                    <x-ui.button variant="danger" type="submit">Revoke</x-ui.button>
                </form>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No share links yet.</p>
        @endforelse
        </div>
    </x-ui.card>
@endsection
