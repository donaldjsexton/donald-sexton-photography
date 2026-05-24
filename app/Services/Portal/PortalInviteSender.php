<?php

namespace App\Services\Portal;

use App\Mail\PortalInvite;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class PortalInviteSender
{
    /**
     * Email a client a 7-day magic link to set up their portal account. No-op
     * (returns false) when the client already has a password, since portal
     * access is already established.
     *
     * @throws \Throwable when the mail transport fails
     */
    public function send(Client $client): bool
    {
        if ($client->password !== null) {
            return false;
        }

        $setupUrl = URL::temporarySignedRoute(
            'portal.invite.show',
            now()->addDays(7),
            ['client' => $client->uuid],
        );

        Mail::to($client->email, $client->displayName())
            ->send(new PortalInvite($client, $setupUrl));

        return true;
    }
}
