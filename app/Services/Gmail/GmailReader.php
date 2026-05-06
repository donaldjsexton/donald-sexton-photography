<?php

namespace App\Services\Gmail;

interface GmailReader
{
    public function isAvailable(): bool;

    public function connectedEmail(): ?string;

    /**
     * Find all Gmail threads exchanged with the given email within the last
     * $withinDays. Newest thread first.
     *
     * @return array<int, string>
     */
    public function findThreadIdsForEmail(string $email, int $withinDays, int $maxThreads = 25): array;

    /**
     * Fetch all messages in a thread, ordered oldest-first.
     *
     * @return array<int, ParsedGmailMessage>
     */
    public function fetchThreadMessages(string $threadId): array;

    /**
     * Search Gmail messages with a raw query string (Gmail search syntax).
     * Returns up to $maxResults parsed messages, newest first.
     *
     * @return array<int, ParsedGmailMessage>
     */
    public function searchMessages(string $query, int $maxResults = 25): array;
}
