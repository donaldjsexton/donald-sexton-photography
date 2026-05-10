<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Venue;
use Illuminate\Support\Facades\Auth;

class Portal
{
    /**
     * Returns the currently signed-in portal user (Client or Venue), or null.
     */
    public static function user(): Client|Venue|null
    {
        return Auth::guard('client')->user()
            ?? Auth::guard('venue')->user();
    }

    public static function guard(): ?string
    {
        if (Auth::guard('client')->check()) {
            return 'client';
        }
        if (Auth::guard('venue')->check()) {
            return 'venue';
        }

        return null;
    }
}
