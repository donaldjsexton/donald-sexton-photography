<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttachMediaTool;
use App\Mcp\Tools\CreateDraftJournalPostTool;
use App\Mcp\Tools\DeleteDraftJournalPostTool;
use App\Mcp\Tools\CreateDraftPageTool;
use App\Mcp\Tools\CreateDraftStoryTool;
use App\Mcp\Tools\CreateHomepageBlockTool;
use App\Mcp\Tools\CreatePageBlockTool;
use App\Mcp\Tools\CreateRedirectTool;
use App\Mcp\Tools\GenerateAltTextTool;
use App\Mcp\Tools\GenerateSeoTool;
use App\Mcp\Tools\GetBookedJobTool;
use App\Mcp\Tools\GetCalendarAvailabilityTool;
use App\Mcp\Tools\GetContractTool;
use App\Mcp\Tools\GetHomepageTool;
use App\Mcp\Tools\GetInquiryTool;
use App\Mcp\Tools\GetInvoiceTool;
use App\Mcp\Tools\GetJournalPostTool;
use App\Mcp\Tools\GetPageTool;
use App\Mcp\Tools\GetSiteSettingsTool;
use App\Mcp\Tools\GetVenueTool;
use App\Mcp\Tools\ListBookedJobsTool;
use App\Mcp\Tools\ListClientsTool;
use App\Mcp\Tools\ListContractsTool;
use App\Mcp\Tools\ListInquiriesTool;
use App\Mcp\Tools\ListInvoicesTool;
use App\Mcp\Tools\ListJournalPostsTool;
use App\Mcp\Tools\ListMediaTool;
use App\Mcp\Tools\ListPagesTool;
use App\Mcp\Tools\ListProposalsTool;
use App\Mcp\Tools\ListRedirectsTool;
use App\Mcp\Tools\ListVenuesTool;
use App\Mcp\Tools\LookupStoryTool;
use App\Mcp\Tools\PublishJournalPostTool;
use App\Mcp\Tools\PublishPageTool;
use App\Mcp\Tools\PublishStoryTool;
use App\Mcp\Tools\ReorderHomepageBlocksTool;
use App\Mcp\Tools\ReorderPageBlocksTool;
use App\Mcp\Tools\UpdateDraftStoryTool;
use App\Mcp\Tools\UpdateHomepageBlockTool;
use App\Mcp\Tools\UpdateHomepageSettingsTool;
use App\Mcp\Tools\UpdateJournalPostTool;
use App\Mcp\Tools\UpdateMediaAltTextTool;
use App\Mcp\Tools\UpdatePageBlockTool;
use App\Mcp\Tools\UpdatePageTool;
use App\Mcp\Tools\UpdateRedirectTool;
use App\Mcp\Tools\UpdateSiteSettingsTool;
use App\Mcp\Tools\UpdateVenueTool;
use App\Mcp\Tools\UploadMediaTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Donald Sexton Photography CMS')]
#[Version('1.0.0')]
#[Instructions('Manage wedding story drafts, pages, page blocks, journal posts, venues, media alt text, and redirects. Read a record before editing it. Upload images with an idempotency key, then set the ordered gallery and hero on the draft. Create and update tools only touch drafts and never publish; publish_story, publish_page, and publish_journal_post are the only tools that make content public. Venue, alt text, and redirect changes are live immediately. Inquiries, clients, booked jobs, and calendar availability are read-only and redacted. Invoices, contracts, and proposals are read-only. Site settings and homepage changes are live and limited to an allowlist; secrets are never returned. Only the tools your credential allows are listed.')]
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
        AttachMediaTool::class,
        CreateDraftJournalPostTool::class,
        DeleteDraftJournalPostTool::class,
        CreateDraftPageTool::class,
        CreatePageBlockTool::class,
        CreateRedirectTool::class,
        GenerateAltTextTool::class,
        GenerateSeoTool::class,
        GetJournalPostTool::class,
        GetPageTool::class,
        GetVenueTool::class,
        ListJournalPostsTool::class,
        ListMediaTool::class,
        ListPagesTool::class,
        ListRedirectsTool::class,
        ListVenuesTool::class,
        PublishJournalPostTool::class,
        PublishPageTool::class,
        ReorderPageBlocksTool::class,
        UpdateJournalPostTool::class,
        UpdateMediaAltTextTool::class,
        UpdatePageBlockTool::class,
        UpdatePageTool::class,
        UpdateRedirectTool::class,
        UpdateVenueTool::class,
        GetBookedJobTool::class,
        GetCalendarAvailabilityTool::class,
        GetInquiryTool::class,
        ListBookedJobsTool::class,
        ListClientsTool::class,
        ListInquiriesTool::class,
        CreateHomepageBlockTool::class,
        GetContractTool::class,
        GetHomepageTool::class,
        GetInvoiceTool::class,
        GetSiteSettingsTool::class,
        ListContractsTool::class,
        ListInvoicesTool::class,
        ListProposalsTool::class,
        ReorderHomepageBlocksTool::class,
        UpdateHomepageBlockTool::class,
        UpdateHomepageSettingsTool::class,
        UpdateSiteSettingsTool::class,
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
