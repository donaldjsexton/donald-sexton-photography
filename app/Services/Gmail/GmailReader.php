<?php

namespace App\Services\Gmail;

interface GmailReader
{
    public function isAvailable(): bool;

    public function connectedEmail(): ?string;

    /**
     * Find the most recent Gmail thread exchanged with the given email
     * within the last $withinDays. Returns the threadId or null.
     */
    public function findThreadIdForEmail(string $email, int $withinDays): ?string;

    /**
     * Fetch all messages in a thread, ordered oldest-first.
     *
     * @return array<int, ParsedGmailMessage>
     */
    public function fetchThreadMessages(string $threadId): array;
}
