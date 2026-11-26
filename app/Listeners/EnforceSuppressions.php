<?php

namespace App\Listeners;

use App\Models\Contact;
use App\Services\Crm\ContactDirectory;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * The one send gate. Runs for every outgoing message. Returning false from a
 * MessageSending listener cancels the send.
 *
 * Marketing/outreach (any Mailable without the IsTransactional trait): blocked if
 * any recipient is suppressed.
 * Transactional: blocked for a suppressed recipient unless they are an active client.
 */
class EnforceSuppressions
{
    public const CATEGORY_HEADER = 'X-DSP-Mail-Category';

    public function __construct(private readonly ContactDirectory $directory) {}

    public function handle(MessageSending $event): ?bool
    {
        $transactional = $this->isTransactional($event);

        $recipients = [
            ...$event->message->getTo(),
            ...$event->message->getCc(),
            ...$event->message->getBcc(),
        ];

        foreach ($recipients as $address) {
            /** @var Address $address */
            $email = $address->getAddress();

            if (! $this->directory->isSuppressed($email)) {
                continue;
            }

            if ($transactional && $this->isActiveClient($email)) {
                continue;
            }

            Log::warning('Email blocked: recipient is on the suppression list', [
                'email' => $email,
                'subject' => $event->message->getSubject(),
                'transactional' => $transactional,
            ]);

            return false;
        }

        return null;
    }

    /** Reads the category header set by the IsTransactional trait. */
    private function isTransactional(MessageSending $event): bool
    {
        $headers = $event->message->getHeaders();
        $name = self::CATEGORY_HEADER;

        if (! $headers->has($name)) {
            return false;
        }

        return $headers->get($name)?->getBodyAsString() === 'transactional';
    }

    private function isActiveClient(string $email): bool
    {
        $contact = Contact::query()->where('email', Contact::normalizeEmail($email))->first();

        return $contact?->isActiveClient() ?? false;
    }
}
