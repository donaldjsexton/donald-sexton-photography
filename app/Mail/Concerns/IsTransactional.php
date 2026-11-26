<?php

namespace App\Mail\Concerns;

use App\Listeners\EnforceSuppressions;
use Illuminate\Mail\Mailables\Headers;

/**
 * Marks a Mailable as transactional (invoices, contracts, portal, replies to
 * someone who wrote to us). The EnforceSuppressions listener reads this header
 * before sending. Mailables without it count as marketing/outreach.
 */
trait IsTransactional
{
    public function headers(): Headers
    {
        return new Headers(text: [EnforceSuppressions::CATEGORY_HEADER => 'transactional']);
    }
}
