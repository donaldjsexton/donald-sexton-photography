<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDraftStoryTool;
use App\Mcp\Tools\LookupStoryTool;
use App\Mcp\Tools\PublishStoryTool;
use App\Mcp\Tools\UpdateDraftStoryTool;
use App\Mcp\Tools\UploadMediaTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Donald Sexton Photography CMS')]
#[Version('1.0.0')]
#[Instructions('Manage wedding story drafts. Look up a story before editing it. Upload images with an idempotency key, then set the ordered gallery and hero on the draft. create_draft_story and update_draft_story never publish. Call publish_story only when the draft should become public.')]
class CmsServer extends Server
{
    /**
     * @var array<int, class-string>
     */
    protected array $tools = [
        LookupStoryTool::class,
        CreateDraftStoryTool::class,
        UpdateDraftStoryTool::class,
        UploadMediaTool::class,
        PublishStoryTool::class,
    ];

    /**
     * @var array<int, class-string>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string>
     */
    protected array $prompts = [];
}
