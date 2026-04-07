<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ImportMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'source_table',
        'source_id',
        'source_url',
        'target_type',
        'target_id',
    ];

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
