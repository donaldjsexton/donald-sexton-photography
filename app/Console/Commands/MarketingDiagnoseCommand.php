<?php

namespace App\Console\Commands;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Services\GoogleSearchConsole;
use App\Services\MarketingHealth;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

#[Signature('marketing:diagnose
    {--thin-words=80 : Word count below which a published story or post body counts as thin}
    {--json : Output the raw report as JSON instead of a formatted summary}
')]
#[Description('Pull GSC/GA4 signals plus a content + thin-page audit into one marketing report.')]
class MarketingDiagnoseCommand extends Command
{
    public function handle(MarketingHealth $health, GoogleSearchConsole $searchConsole): int
    {
        $thinWords = max(0, (int) $this->option('thin-words'));
        $siteSettings = SiteSetting::current();

        $report = [
            'signals' => $health->snapshot($siteSettings)['signals'],
            'seoCoverage' => $this->seoCoverage(),
            'inventory' => $this->inventory(),
            'thin' => $this->thinContent($thinWords),
            'topQueries' => $this->topQueries($siteSettings, $searchConsole),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->render($report, $thinWords);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function render(array $report, int $thinWords): void
    {
        $this->newLine();
        $this->info('Marketing diagnosis · '.now()->toDayDateTimeString());

        $this->section('Signals (last 28 days)');
        foreach ($report['signals'] as $signal) {
            $this->line(sprintf('  %-18s %s', $signal['label'].':', $signal['value']));
            $this->line('  '.str_repeat(' ', 18).$signal['description']);
        }

        $this->section('SEO metadata coverage');
        foreach ($report['seoCoverage'] as $row) {
            $this->line(sprintf('  %-18s %-5s %s', $row['label'].':', $row['value'], $row['context']));
        }

        $this->section('Content inventory');
        foreach ($report['inventory'] as $row) {
            $this->line(sprintf('  %-18s %s', $row['label'].':', $row['value']));
        }

        $this->section('Thin / incomplete pages (fix these first)');
        $anyThin = false;
        foreach ($report['thin'] as $group) {
            if ($group['count'] === 0) {
                continue;
            }
            $anyThin = true;
            $this->line(sprintf('  %s — %d', $group['label'], $group['count']));
            foreach ($group['examples'] as $example) {
                $this->line('    · '.$example);
            }
        }
        if (! $anyThin) {
            $this->line('  Nothing flagged — published pages have heroes and bodies over '.$thinWords.' words.');
        }

        if ($report['topQueries'] !== []) {
            $this->section('Top Search Console queries (28 days)');
            foreach ($report['topQueries'] as $query) {
                $this->line(sprintf('  %4d clicks · %6d impr · %s', $query['clicks'], $query['impressions'], $query['query']));
            }
        }

        $this->newLine();
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('<comment>'.$title.'</comment>');
    }

    /**
     * @return array<int, array{label: string, value: string, context: string}>
     */
    private function seoCoverage(): array
    {
        $types = [
            'Pages' => Page::query(),
            'Wedding Stories' => WeddingStory::query(),
            'Journal Posts' => JournalPost::query(),
            'Venues' => Venue::query(),
        ];

        $rows = [];

        foreach ($types as $label => $query) {
            $total = (clone $query)->count();
            $withMeta = (clone $query)
                ->whereNotNull('seo_title')
                ->where('seo_title', '!=', '')
                ->whereNotNull('seo_description')
                ->where('seo_description', '!=', '')
                ->count();

            $coverage = $total > 0 ? (int) round(($withMeta / $total) * 100) : 0;

            $rows[] = [
                'label' => $label,
                'value' => $coverage.'%',
                'context' => $withMeta.' of '.$total.' have title and description.',
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function inventory(): array
    {
        return [
            [
                'label' => 'Wedding Stories',
                'value' => $this->publishedDraft(WeddingStory::query()),
            ],
            [
                'label' => 'Journal Posts',
                'value' => $this->publishedDraft(JournalPost::query()),
            ],
            [
                'label' => 'Venues',
                'value' => sprintf(
                    '%d total · %d with a hero image · %d linked to a wedding',
                    Venue::query()->count(),
                    Venue::query()->whereNotNull('hero_media_id')->count(),
                    Venue::query()->whereHas('weddingStories')->count(),
                ),
            ],
        ];
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function publishedDraft($query): string
    {
        $published = (clone $query)->where('status', 'published')->count();
        $total = (clone $query)->count();

        return sprintf('%d published · %d draft', $published, max(0, $total - $published));
    }

    /**
     * @return array<int, array{label: string, count: int, examples: array<int, string>}>
     */
    private function thinContent(int $thinWords): array
    {
        $publishedStories = WeddingStory::query()->where('status', 'published');
        $publishedPosts = JournalPost::query()->where('status', 'published');

        return [
            [
                'label' => 'Published wedding stories with no hero image',
                ...$this->summarise((clone $publishedStories)->whereNull('hero_media_id')->get()),
            ],
            [
                'label' => 'Published wedding stories with a thin body (< '.$thinWords.' words)',
                ...$this->summarise((clone $publishedStories)->get()->filter(fn (WeddingStory $s) => $this->wordCount($s->body) < $thinWords)),
            ],
            [
                'label' => 'Published journal posts with no hero image',
                ...$this->summarise((clone $publishedPosts)->whereNull('hero_media_id')->get()),
            ],
            [
                'label' => 'Venues with no linked weddings',
                ...$this->summarise(Venue::query()->whereDoesntHave('weddingStories')->get()),
            ],
            [
                'label' => 'Venues with no hero image',
                ...$this->summarise(Venue::query()->whereNull('hero_media_id')->get()),
            ],
        ];
    }

    /**
     * @param  Collection<int, Model>  $records
     * @return array{count: int, examples: array<int, string>}
     */
    private function summarise($records): array
    {
        return [
            'count' => $records->count(),
            'examples' => $records->take(5)
                ->map(fn (Model $record) => '#'.$record->getKey().' '.($record->title ?? $record->name ?? '(untitled)'))
                ->values()
                ->all(),
        ];
    }

    private function wordCount(?string $html): int
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? 0 : str_word_count($text);
    }

    /**
     * @return array<int, array{query: string, clicks: int, impressions: int}>
     */
    private function topQueries(SiteSetting $siteSettings, GoogleSearchConsole $searchConsole): array
    {
        if (! $siteSettings->googleIsConnected()
            || ! $siteSettings->googleHasScope('https://www.googleapis.com/auth/webmasters.readonly')) {
            return [];
        }

        $snapshot = $searchConsole->snapshot(rtrim((string) config('app.url'), '/'));

        return $snapshot['topQueries'] ?? [];
    }
}
