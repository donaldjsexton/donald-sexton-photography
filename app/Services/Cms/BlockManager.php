<?php

namespace App\Services\Cms;

use App\Models\Block;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BlockManager
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys((array) config('blocks.types')))],
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'data' => ['nullable', 'array'],
            'is_visible' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(Model $owner, array $validated): Block
    {
        /** @var Block */
        return $owner->allBlocks()->create([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? true),
            'sort_order' => $validated['sort_order'] ?? $this->nextSortOrder($owner),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(Block $block, array $validated): void
    {
        $block->update([
            'type' => $validated['type'],
            'heading' => $validated['heading'] ?? null,
            'subheading' => $validated['subheading'] ?? null,
            'body' => $validated['body'] ?? null,
            'data' => $this->cleanData($validated['data'] ?? null),
            'is_visible' => (bool) ($validated['is_visible'] ?? false),
            'sort_order' => $validated['sort_order'] ?? $block->sort_order,
        ]);
    }

    /**
     * @param  list<int|string>  $blockIds
     */
    public function reorder(Model $owner, array $blockIds): void
    {
        $ownedIds = $owner->allBlocks()->pluck('id')->all();
        $position = 0;

        foreach ($blockIds as $id) {
            $id = (int) $id;

            if (! in_array($id, $ownedIds, true)) {
                continue;
            }

            Block::whereKey($id)->update(['sort_order' => $position++]);
        }
    }

    public function owns(Model $owner, Block $block): bool
    {
        return $block->blockable_type === $owner->getMorphClass() && (int) $block->blockable_id === (int) $owner->getKey();
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function cleanData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $filtered = array_filter(
            $data,
            fn ($value) => $value !== null && $value !== '',
        );

        return $filtered === [] ? null : $filtered;
    }

    private function nextSortOrder(Model $owner): int
    {
        return (int) $owner->allBlocks()->max('sort_order') + 1;
    }
}
