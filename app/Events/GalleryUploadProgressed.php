<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted once per file as a gallery upload batch is ingested, giving the admin
 * a live "heartbeat" of progress: each file's outcome (created, duplicate, or
 * failed), its position in the batch, and — when created or duplicated — the
 * thumbnail URL so the grid can fill in without a page refresh.
 */
class GalleryUploadProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{uuid:string, id:int, thumb_url:string, original_name:?string}|null  $photo
     */
    public function __construct(
        public int $galleryId,
        public int $albumId,
        public string $batchId,
        public int $index,
        public int $total,
        public string $status,
        public ?array $photo = null,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('galleries.'.$this->galleryId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'upload.progressed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'album_id' => $this->albumId,
            'batch_id' => $this->batchId,
            'index' => $this->index,
            'total' => $this->total,
            'status' => $this->status,
            'photo' => $this->photo,
            'reason' => $this->reason,
        ];
    }
}
