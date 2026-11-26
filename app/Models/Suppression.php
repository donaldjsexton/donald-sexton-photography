<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suppression extends Model
{
    use BelongsToSite;

    public const SOURCES = ['unsubscribe', 'manual', 'bounce', 'complaint', 'imported'];

    protected $fillable = ['email', 'contact_id', 'reason', 'source', 'suppressed_at'];

    protected function casts(): array
    {
        return ['suppressed_at' => 'datetime'];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = Contact::normalizeEmail($value);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
