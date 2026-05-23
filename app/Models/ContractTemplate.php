<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractTemplate extends Model
{
    use BelongsToSite;

    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'title',
        'description',
        'body',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }
}
