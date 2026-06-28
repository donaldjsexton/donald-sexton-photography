<?php

use App\Models\Gallery;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Live upload progress for a gallery. Only authenticated admins (the default
 * web guard) may listen, and only for a gallery that resolves within the
 * current tenant — the global site scope on the lookup enforces that.
 */
Broadcast::channel('galleries.{galleryId}', function ($user, int $galleryId): bool {
    return Gallery::query()->whereKey($galleryId)->exists();
});
