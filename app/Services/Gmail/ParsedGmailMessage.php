<?php

namespace App\Services\Gmail;

use Illuminate\Support\Carbon;

class ParsedGmailMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
        public readonly string $subject,
        public readonly string $bodyPlain,
        public readonly Carbon $sentAt,
        public readonly bool $hasAttachments,
    ) {}
}
