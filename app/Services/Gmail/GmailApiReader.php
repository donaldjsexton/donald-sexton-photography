<?php

namespace App\Services\Gmail;

use App\Services\GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message as GmailMessage;
use Google\Service\Gmail\MessagePart;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class GmailApiReader implements GmailReader
{
    private const READONLY_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    public function __construct(private readonly GoogleClient $googleClient) {}

    public function isAvailable(): bool
    {
        return $this->gmail() !== null;
    }

    public function connectedEmail(): ?string
    {
        return $this->googleClient->connectedEmail();
    }

    public function findThreadIdsForEmail(string $email, int $withinDays, int $maxThreads = 25): array
    {
        $gmail = $this->gmail();

        if ($gmail === null) {
            return [];
        }

        $query = sprintf('(from:%s OR to:%s) newer_than:%dd', $email, $email, $withinDays);

        try {
            $response = $gmail->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $maxThreads * 5,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gmail thread lookup failed: '.$e->getMessage());

            return [];
        }

        $threadIds = [];

        foreach ($response->getMessages() ?? [] as $message) {
            $threadId = $message->getThreadId();

            if ($threadId === null || $threadId === '') {
                continue;
            }

            if (in_array($threadId, $threadIds, true)) {
                continue;
            }

            $threadIds[] = $threadId;

            if (count($threadIds) >= $maxThreads) {
                break;
            }
        }

        return $threadIds;
    }

    public function fetchThreadMessages(string $threadId): array
    {
        $gmail = $this->gmail();

        if ($gmail === null) {
            return [];
        }

        try {
            $thread = $gmail->users_threads->get('me', $threadId, ['format' => 'full']);
        } catch (\Throwable $e) {
            Log::warning("Gmail thread fetch failed ({$threadId}): ".$e->getMessage());

            return [];
        }

        $parsed = [];

        foreach ($thread->getMessages() ?? [] as $message) {
            $parsed[] = $this->parseMessage($message, $threadId);
        }

        usort($parsed, fn (ParsedGmailMessage $a, ParsedGmailMessage $b) => $a->sentAt <=> $b->sentAt);

        return $parsed;
    }

    public function searchMessages(string $query, int $maxResults = 25): array
    {
        $gmail = $this->gmail();

        if ($gmail === null) {
            return [];
        }

        try {
            $response = $gmail->users_messages->listUsersMessages('me', [
                'q' => $query,
                'maxResults' => $maxResults,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gmail search failed: '.$e->getMessage());

            return [];
        }

        $stubs = $response->getMessages() ?? [];

        if ($stubs === []) {
            return [];
        }

        $parsed = [];

        foreach ($stubs as $stub) {
            try {
                $full = $gmail->users_messages->get('me', $stub->getId(), ['format' => 'full']);
            } catch (\Throwable $e) {
                Log::warning("Gmail message fetch failed ({$stub->getId()}): ".$e->getMessage());

                continue;
            }

            $parsed[] = $this->parseMessage($full, $stub->getThreadId() ?? '');
        }

        usort($parsed, fn (ParsedGmailMessage $a, ParsedGmailMessage $b) => $b->sentAt <=> $a->sentAt);

        return $parsed;
    }

    private function gmail(): ?Gmail
    {
        $client = $this->googleClient->client();

        if ($client === null) {
            return null;
        }

        // Accept either read-only or the broader full-access scope.
        if (! $this->googleClient->hasAnyScope([self::READONLY_SCOPE, 'https://mail.google.com/'])) {
            return null;
        }

        return new Gmail($client);
    }

    private function parseMessage(GmailMessage $message, string $threadId): ParsedGmailMessage
    {
        $payload = $message->getPayload();
        $headers = [];

        foreach ($payload?->getHeaders() ?? [] as $header) {
            $headers[strtolower($header->getName())] = $header->getValue();
        }

        [$fromName, $fromEmail] = $this->parseAddress($headers['from'] ?? '');

        $bodyPlain = $this->extractPlainText($payload);
        $hasAttachments = $this->detectAttachments($payload);

        $sentAt = isset($headers['date'])
            ? Carbon::parse($headers['date'])
            : Carbon::createFromTimestampMs((int) $message->getInternalDate());

        return new ParsedGmailMessage(
            id: $message->getId(),
            threadId: $threadId,
            fromEmail: strtolower($fromEmail),
            fromName: $fromName,
            subject: $headers['subject'] ?? '',
            bodyPlain: trim($bodyPlain),
            sentAt: $sentAt,
            hasAttachments: $hasAttachments,
        );
    }

    /**
     * @return array{0: ?string, 1: string} [name, email]
     */
    private function parseAddress(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [null, ''];
        }

        if (preg_match('/^(.*?)<([^>]+)>$/', $raw, $m)) {
            $name = trim(trim($m[1]), '"');

            return [$name !== '' ? $name : null, trim($m[2])];
        }

        return [null, $raw];
    }

    private function extractPlainText(?MessagePart $part): string
    {
        if ($part === null) {
            return '';
        }

        $mime = $part->getMimeType();

        if ($mime === 'text/plain') {
            return $this->decodeBody($part);
        }

        foreach ($part->getParts() ?? [] as $sub) {
            $text = $this->extractPlainText($sub);

            if ($text !== '') {
                return $text;
            }
        }

        // Fallback: if nothing else, try decoding text/html stripped of tags.
        if ($mime === 'text/html') {
            $html = $this->decodeBody($part);

            return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }

    private function decodeBody(MessagePart $part): string
    {
        $data = $part->getBody()?->getData();

        if (! $data) {
            return '';
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }

    private function detectAttachments(?MessagePart $part): bool
    {
        if ($part === null) {
            return false;
        }

        if ($part->getFilename()) {
            return true;
        }

        foreach ($part->getParts() ?? [] as $sub) {
            if ($this->detectAttachments($sub)) {
                return true;
            }
        }

        return false;
    }
}
