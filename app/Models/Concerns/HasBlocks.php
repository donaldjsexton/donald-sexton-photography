<?php

namespace App\Models\Concerns;

use App\Models\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasBlocks
{
    /**
     * Visible blocks in display order, for public rendering.
     *
     * @return MorphMany<Block, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')
            ->visible()
            ->ordered();
    }

    /**
     * Every block including hidden ones, for admin management.
     *
     * @return MorphMany<Block, $this>
     */
    public function allBlocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable')->ordered();
    }
}
