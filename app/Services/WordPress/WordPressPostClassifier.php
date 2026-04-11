<?php

namespace App\Services\WordPress;

use App\Models\JournalPost;
use Illuminate\Support\Str;

class WordPressPostClassifier
{
    public function classifyImportItem(\SimpleXMLElement $item): string
    {
        $categories = [];
        $tags = [];

        foreach ($item->category ?? [] as $term) {
            $slug = Str::slug(trim((string) ($term['nicename'] ?: $term)));

            if ($slug === '') {
                continue;
            }

            if ((string) $term['domain'] === 'category') {
                $categories[] = $slug;
                continue;
            }

            if ((string) $term['domain'] === 'post_tag') {
                $tags[] = $slug;
            }
        }

        return $this->classify(
            $categories,
            $tags,
            (string) $item->title,
            (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded
        );
    }

    public function classifyJournalPost(JournalPost $post): string
    {
        return $this->classify(
            $post->categories()->pluck('slug')->all(),
            $post->tags()->pluck('slug')->all(),
            $post->title,
            $post->body
        );
    }

    public function classify(array $categorySlugs, array $tagSlugs = [], ?string $title = null, ?string $body = null): string
    {
        $categories = $this->normalizeSlugs($categorySlugs);
        $tags = $this->normalizeSlugs($tagSlugs);
        $titleText = Str::lower(trim((string) $title));
        $bodyText = Str::lower(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $body)) ?? ''));

        if ($this->containsFragment($categories, ['engagement', 'proposal'])) {
            return 'engagement';
        }

        if ($this->containsFragment($categories, ['advice', 'planning', 'tips', 'guide', 'guides', 'faq', 'question', 'questions', 'resource', 'resources'])) {
            return 'advice';
        }

        if ($this->containsFragment($categories, ['venue', 'venues'])) {
            return 'venue';
        }

        if ($this->containsExact($categories, ['real-wedding', 'real-weddings', 'realwedding', 'realweddings'])) {
            return 'real_wedding';
        }

        if ($this->containsExact($categories, ['wedding', 'weddings', 'elopement', 'elopements'])) {
            return 'real_wedding';
        }

        if ($this->containsFragment($tags, ['engagement'])) {
            return 'engagement';
        }

        if ($this->looksLikeAdvice($titleText, $bodyText, $tags)) {
            return 'advice';
        }

        if ($this->looksLikeVenue($titleText, $bodyText)) {
            return 'venue';
        }

        if ($this->looksLikeRealWedding($titleText, $bodyText, $tags)) {
            return 'real_wedding';
        }

        return 'advice';
    }

    private function normalizeSlugs(array $slugs): array
    {
        return collect($slugs)
            ->map(fn ($slug) => Str::slug((string) $slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function containsExact(array $haystack, array $needles): bool
    {
        $needles = $this->normalizeSlugs($needles);

        foreach ($haystack as $value) {
            if (in_array($value, $needles, true)) {
                return true;
            }
        }

        return false;
    }

    private function containsFragment(array $haystack, array $needles): bool
    {
        foreach ($haystack as $value) {
            foreach ($needles as $needle) {
                if (str_contains($value, Str::slug($needle))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function looksLikeAdvice(string $title, string $body, array $tags): bool
    {
        if ($this->containsFragment($tags, ['tips', 'advice', 'planning', 'guide', 'faq', 'question', 'questions'])) {
            return true;
        }

        return Str::contains($title, [
            'guide',
            'tips',
            'how to',
            'how i',
            'why ',
            'what ',
            'when ',
            'q:',
            'questions',
            'mistake',
            'mistakes',
            'resources',
            'planning',
            'photography style',
        ]) || Str::contains($body, [
            'interested in learning more',
            'set up your free bridal consultation',
        ]);
    }

    private function looksLikeVenue(string $title, string $body): bool
    {
        return Str::contains($title, ['venue spotlight', 'venue guide'])
            || Str::contains($body, ['venue spotlight', 'wedding venue']);
    }

    private function looksLikeRealWedding(string $title, string $body, array $tags): bool
    {
        if ($this->containsExact($tags, ['wedding', 'weddings', 'elopement', 'elopements'])) {
            return true;
        }

        return Str::contains($title, [
            ' wedding at ',
            ' wedding on ',
            ' wedding in ',
            ' wedding |',
            ' wedding -',
            ' elopement ',
            ' sneak peek',
        ]) || Str::contains($body, [
            'the ceremony',
            'the reception',
            'their day',
            'their wedding',
        ]);
    }
}
