<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportRun extends Model
{
    use BelongsToSite;
    use HasFactory;

    protected $fillable = [
        'source_type',
        'status',
        'started_at',
        'finished_at',
        'summary_json',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary_json' => 'array',
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ImportMapping::class);
    }
}
