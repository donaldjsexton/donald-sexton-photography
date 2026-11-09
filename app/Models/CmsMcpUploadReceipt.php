<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsMcpUploadReceipt extends Model
{
    protected $fillable = [
        'cms_mcp_credential_id',
        'idempotency_key',
        'content_sha256',
        'media_id',
    ];

    /**
     * @return BelongsTo<CmsMcpCredential, $this>
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(CmsMcpCredential::class, 'cms_mcp_credential_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
