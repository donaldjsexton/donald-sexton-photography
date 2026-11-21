<?php

namespace App\Services\Cms;

use App\Models\Redirect;
use App\Tenancy\CurrentSite;
use Illuminate\Validation\Rule;

class RedirectManager
{
    public const SOURCE = 'cms-mcp';

    /**
     * Targets must stay on this site, so a redirect cannot be used as an open
     * redirect to another host.
     *
     * @return array<string, mixed>
     */
    public function rules(?Redirect $redirect = null): array
    {
        return [
            'from_path' => [
                'required', 'string', 'max:255', 'regex:#^/(?!/)[^\s?\#]*$#',
                Rule::unique('redirects', 'from_path')->where('site_id', app(CurrentSite::class)->id())->ignore($redirect?->id),
            ],
            'to_path' => ['required', 'string', 'max:255', 'regex:#^/(?!/)\S*$#', 'different:from_path'],
            'status_code' => ['nullable', 'integer', Rule::in([301, 302, 307, 308])],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function save(Redirect $redirect, array $validated): void
    {
        $redirect->fill([
            ...$validated,
            'status_code' => $validated['status_code'] ?? $redirect->status_code ?? 301,
            'source' => $redirect->source ?? self::SOURCE,
        ]);
        $redirect->save();
    }
}
