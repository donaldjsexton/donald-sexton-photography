<?php

namespace App\Services\Gmail;

use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GmailSyncService
{
    public function __construct(private readonly GmailReader $reader) {}

    /**
     * Sync Gmail threads into inquiries / inquiry_messages.
     *
     * @return array{checked: int, linked: int, new_messages: int}
     */
    public function sync(int $withinDays = 90): array
    {
        if (! $this->reader->isAvailable()) {
            return ['checked' => 0, 'linked' => 0, 'new_messages' => 0];
        }

        $connectedEmail = strtolower((string) $this->reader->connectedEmail());
        $checked = 0;
        $linked = 0;
        $newMessages = 0;

        Inquiry::query()
            ->where('status', '!=', 'archived')
            ->orderBy('id')
            ->chunkById(100, function ($inquiries) use ($connectedEmail, $withinDays, &$checked, &$linked, &$newMessages): void {
                foreach ($inquiries as $inquiry) {
                    $checked++;

                    if (! $inquiry->gmail_thread_id) {
                        $threadId = $this->reader->findThreadIdForEmail($inquiry->email, $withinDays);

                        if ($threadId === null) {
                            continue;
                        }

                        $inquiry->update(['gmail_thread_id' => $threadId]);
                        $linked++;
                    }

                    $newMessages += $this->upsertThread($inquiry, $connectedEmail);
                }
            });

        SiteSetting::query()->first()?->update(['gmail_last_synced_at' => now()]);

        return ['checked' => $checked, 'linked' => $linked, 'new_messages' => $newMessages];
    }

    private function upsertThread(Inquiry $inquiry, string $connectedEmail): int
    {
        $messages = $this->reader->fetchThreadMessages($inquiry->gmail_thread_id);

        if ($messages === []) {
            return 0;
        }

        $existingIds = InquiryMessage::query()
            ->where('inquiry_id', $inquiry->id)
            ->whereIn('gmail_message_id', array_map(fn (ParsedGmailMessage $m) => $m->id, $messages))
            ->pluck('gmail_message_id')
            ->all();

        $created = 0;

        foreach ($messages as $message) {
            if (in_array($message->id, $existingIds, true)) {
                continue;
            }

            $isOutbound = $connectedEmail !== '' && $message->fromEmail === $connectedEmail;

            $body = $message->bodyPlain;

            if ($message->hasAttachments) {
                $body = rtrim($body)."\n\n[Gmail attachments present — view in Gmail.]";
            }

            if ($body === '') {
                $body = '(empty message)';
            }

            $senderName = $message->fromName ?? Str::before($message->fromEmail, '@');

            if ($isOutbound) {
                $local = $this->findLocallyCreatedOutbound($inquiry, $message);

                if ($local !== null) {
                    $local->update([
                        'sender_name' => $senderName,
                        'sender_email' => $message->fromEmail,
                        'sent_at' => $message->sentAt,
                        'gmail_message_id' => $message->id,
                    ]);

                    continue;
                }
            }

            $inquiry->messages()->create([
                'direction' => $isOutbound ? 'outbound' : 'inbound',
                'body' => $body,
                'sender_name' => $senderName,
                'sender_email' => $message->fromEmail,
                'sent_at' => $message->sentAt,
                'gmail_message_id' => $message->id,
            ]);

            $this->applySideEffects($inquiry, $isOutbound, $message->sentAt);
            $created++;
        }

        return $created;
    }

    /**
     * When the admin sends a reply through the app, an outbound InquiryMessage
     * is created before the email leaves the building, so it has no Gmail ID.
     * The next Gmail sync would otherwise insert a second row for the same
     * email. Match it back to the locally-created row by direction + sent_at
     * proximity so we update in place instead of duplicating.
     */
    private function findLocallyCreatedOutbound(Inquiry $inquiry, ParsedGmailMessage $message): ?InquiryMessage
    {
        return $inquiry->messages()
            ->where('direction', 'outbound')
            ->whereNull('gmail_message_id')
            ->whereBetween('sent_at', [
                $message->sentAt->copy()->subMinutes(10),
                $message->sentAt->copy()->addMinutes(10),
            ])
            ->orderBy('sent_at', 'desc')
            ->first();
    }

    private function applySideEffects(Inquiry $inquiry, bool $isOutbound, Carbon $sentAt): void
    {
        $updates = [];

        if ($isOutbound && $inquiry->first_responded_at === null) {
            $updates['first_responded_at'] = $sentAt;
        }

        if (! $isOutbound && $inquiry->status === 'new') {
            $updates['status'] = 'active';
        }

        if ($updates !== []) {
            $inquiry->update($updates);
        }
    }
}
