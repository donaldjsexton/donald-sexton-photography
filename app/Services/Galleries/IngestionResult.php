<?php

namespace App\Services\Galleries;

use App\Models\Photo;

/**
 * Immutable record of an ingestion attempt: the resulting photo (for created or
 * duplicate outcomes) and a machine-readable reason when ingestion failed.
 */
class IngestionResult
{
    public function __construct(
        public readonly IngestionStatus $status,
        public readonly ?Photo $photo = null,
        public readonly ?string $reason = null,
    ) {}

    public static function created(Photo $photo): self
    {
        return new self(IngestionStatus::Created, $photo);
    }

    public static function duplicate(Photo $photo): self
    {
        return new self(IngestionStatus::Duplicate, $photo);
    }

    public static function failed(string $reason): self
    {
        return new self(IngestionStatus::Failed, null, $reason);
    }

    public function isCreated(): bool
    {
        return $this->status === IngestionStatus::Created;
    }

    public function isDuplicate(): bool
    {
        return $this->status === IngestionStatus::Duplicate;
    }

    public function isFailed(): bool
    {
        return $this->status === IngestionStatus::Failed;
    }
}
