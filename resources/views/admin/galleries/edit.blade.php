@extends('layouts.admin')

@section('title', 'Edit Gallery')
@section('eyebrow', 'Content')
@section('heading', $gallery->title)
@section('subheading', 'Manage albums, photos, and share links for this gallery.')
@section('header_actions')
    <a class="cta-secondary" href="{{ route('admin.galleries.index') }}">Back to galleries</a>
@endsection
@section('content')
    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <style>
        .gallery-photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        @media (min-width: 640px) { .gallery-photo-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); } }
        .gallery-photo { border: 1px solid #e7d8c5; border-radius: 8px; overflow: hidden; background: #faf5ee; }
        .gallery-photo img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; background: #e7d8c5; }
        .gallery-photo__bar { display: flex; gap: 6px; padding: 6px; }
        .gallery-photo__bar button { flex: 1; min-height: 36px; border: 0; border-radius: 6px; cursor: pointer; font-size: 12px; }
        .album-block { margin-top: 16px; }
        .album-block__head { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; }
        .inline-form { display: inline; }
        .share-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #efe3d3; }
        .share-row code { word-break: break-all; }
        .upload-status { margin: 10px 0 0; padding: 8px 12px; border-radius: 6px; font-size: 13px; background: #f4ece0; color: #5b4636; border: 1px solid #e7d8c5; }
        .upload-status[data-tone="success"] { background: #e8f3e8; border-color: #cbe3c9; color: #2f5d34; }
        .upload-status[data-tone="warn"] { background: #fbeee0; border-color: #f0d4b0; color: #8a5a1f; }
        .gallery-photo.is-fresh { animation: gallery-photo-pop .45s ease; outline: 2px solid #b88a4f; outline-offset: -2px; }
        @keyframes gallery-photo-pop { from { opacity: 0; transform: scale(.92); } to { opacity: 1; transform: scale(1); } }
    </style>

    {{-- Gallery settings --}}
    <section class="admin-card">
        <h2>Settings</h2>
        <form method="POST" action="{{ route('admin.galleries.update', $gallery) }}" class="admin-form">
            @csrf
            @method('PUT')
            <div class="field-grid">
                <label>
                    Title
                    <input type="text" name="title" value="{{ old('title', $gallery->title) }}" required>
                </label>
                <label>
                    Visibility
                    <select name="visibility">
                        <option value="private" @selected($gallery->visibility === 'private')>Private (link only)</option>
                        <option value="public" @selected($gallery->visibility === 'public')>Public</option>
                    </select>
                </label>
                <label>
                    Client <span class="meta">(optional)</span>
                    <select name="client_id">
                        <option value="">Unassigned</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected($gallery->client_id === $client->id)>{{ $client->displayName() }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    {{ $gallery->password ? 'Change password' : 'Set password' }} <span class="meta">(optional)</span>
                    <input type="text" name="password" value="" autocomplete="off" placeholder="Leave blank to keep current">
                </label>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="requires_payment" value="1" @checked($gallery->requires_payment)>
                Require the balance to be paid before full-resolution downloads
            </label>
            @if ($gallery->password)
                <label class="checkbox">
                    <input type="checkbox" name="remove_password" value="1"> Remove password protection
                </label>
            @endif
            <div class="form-actions">
                <button class="cta" type="submit">Save settings</button>
            </div>
        </form>
    </section>

    {{-- Albums + photos --}}
    <section class="admin-card">
        <div class="album-block__head">
            <h2>Albums</h2>
            <form method="POST" action="{{ route('admin.galleries.albums.store', $gallery) }}" class="admin-search-form">
                @csrf
                <label>Album name<input type="text" name="name" placeholder="e.g. Ceremony" required></label>
                <input type="hidden" name="visibility" value="private">
                <button class="cta" type="submit" style="border:0;cursor:pointer;">Add album</button>
            </form>
        </div>

        <div data-gallery-uploads
             data-gallery-channel="galleries.{{ $gallery->id }}"
             data-gallery-event=".upload.progressed">
        @forelse ($gallery->albums as $album)
            <div class="album-block">
                <div class="album-block__head">
                    <form method="POST" action="{{ route('admin.galleries.albums.update', [$gallery, $album]) }}" class="admin-search-form">
                        @csrf
                        @method('PUT')
                        <label>Name<input type="text" name="name" value="{{ $album->name }}" required></label>
                        <select name="visibility">
                            <option value="private" @selected($album->visibility === 'private')>Private</option>
                            <option value="public" @selected($album->visibility === 'public')>Public</option>
                        </select>
                        <button class="cta-secondary" type="submit" style="border:0;cursor:pointer;">Rename</button>
                    </form>
                    <form method="POST" action="{{ route('admin.galleries.albums.destroy', [$gallery, $album]) }}"
                          class="inline-form" onsubmit="return confirm('Delete this album and its photos?');">
                        @csrf
                        @method('DELETE')
                        <button class="cta-danger" type="submit">Delete album</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('admin.galleries.albums.photos.store', [$gallery, $album]) }}"
                      enctype="multipart/form-data" class="admin-form" style="margin-top:10px;"
                      data-upload-form
                      data-album-id="{{ $album->id }}"
                      data-upload-url="{{ route('admin.galleries.albums.photos.upload', [$gallery, $album]) }}">
                    @csrf
                    <label>
                        Upload photos <span class="meta">(JPEG, PNG, or WebP)</span>
                        <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    </label>
                    <div class="form-actions">
                        <button class="cta" type="submit">Upload</button>
                    </div>
                </form>

                <p class="upload-status" data-upload-status data-album-id="{{ $album->id }}" hidden></p>

                @if ($album->photos->isEmpty())
                    <p class="meta" data-empty-note>No photos in this album yet.</p>
                @endif
                <div class="gallery-photo-grid" data-photo-grid data-album-id="{{ $album->id }}">
                    @foreach ($album->photos as $photo)
                        <div class="gallery-photo" data-photo-id="{{ $photo->id }}">
                                <img src="{{ route('admin.galleries.photos.thumb', [$gallery, $photo]) }}" alt="{{ $photo->original_name }}" loading="lazy">
                                <div class="gallery-photo__bar">
                                    <form method="POST" action="{{ route('admin.galleries.cover', [$gallery, $photo]) }}" class="inline-form" style="flex:1;">
                                        @csrf
                                        <button class="cta-secondary" type="submit" style="width:100%;"
                                                @disabled($gallery->cover_photo_id === $photo->id)>
                                            {{ $gallery->cover_photo_id === $photo->id ? 'Cover' : 'Set cover' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.galleries.albums.photos.destroy', [$gallery, $album, $photo]) }}" class="inline-form" style="flex:1;"
                                          onsubmit="return confirm('Remove this photo?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="cta-danger" type="submit" style="width:100%;">Remove</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
            </div>
        @empty
            <p class="meta">Add an album to start uploading photos.</p>
        @endforelse
        </div>
    </section>

    {{-- Share links --}}
    <section class="admin-card">
        <h2>Share links</h2>
        <form method="POST" action="{{ route('admin.galleries.shares.store', $gallery) }}" class="admin-form">
            @csrf
            <div class="field-grid">
                <label>
                    Scope
                    <select name="album_id">
                        <option value="">Whole gallery</option>
                        @foreach ($gallery->albums as $album)
                            <option value="{{ $album->id }}">{{ $album->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Expires <span class="meta">(optional)</span><input type="datetime-local" name="expires_at"></label>
                <label>Password <span class="meta">(optional)</span><input type="text" name="password" autocomplete="off"></label>
            </div>
            <div class="form-actions">
                <button class="cta" type="submit">Create share link</button>
            </div>
        </form>

        @forelse ($gallery->shareTokens as $shareToken)
            <div class="share-row">
                <div>
                    <strong>{{ $shareToken->shareable_type === \App\Models\Album::class ? ($shareToken->shareable->name ?? 'Album') : 'Whole gallery' }}</strong>
                    <div class="meta">
                        <code>{{ route('galleries.share.show', $shareToken->token) }}</code>
                    </div>
                    <div class="meta">
                        {{ $shareToken->password ? 'Password protected · ' : '' }}{{ $shareToken->expires_at ? 'Expires '.$shareToken->expires_at->format('M j, Y H:i') : 'No expiry' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.galleries.shares.destroy', [$gallery, $shareToken]) }}"
                      onsubmit="return confirm('Revoke this share link?');">
                    @csrf
                    @method('DELETE')
                    <button class="cta-danger" type="submit">Revoke</button>
                </form>
            </div>
        @empty
            <p class="meta">No share links yet.</p>
        @endforelse
    </section>
@endsection
