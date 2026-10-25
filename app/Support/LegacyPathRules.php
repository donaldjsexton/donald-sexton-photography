<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Tag;

/**
 * Maps the structural paths of the retired WordPress site (blog index,
 * category/tag/author archives, XML sitemaps, standalone pages) onto their
 * closest equivalent here, so inbound links keep their value instead of
 * landing on a 404.
 */
class LegacyPathRules
{
    private const WORDPRESS_SITEMAP = '#^/(?:sitemap_index|wp-sitemap(?:-[a-z0-9-]+)?|(?:post|page|category|tag|author|elementor-hf)-sitemap\d*)\.xml$#';

    /**
     * @return array{to: ?string, status: int}|null
     */
    public static function resolve(string $path): ?array
    {
        $path = '/'.trim($path, '/');

        $exact = config('legacy_redirects.exact', []);

        if (isset($exact[$path])) {
            return ['to' => $exact[$path][0], 'status' => (int) $exact[$path][1]];
        }

        if (preg_match(self::WORDPRESS_SITEMAP, $path) === 1) {
            return ['to' => '/sitemap.xml', 'status' => 301];
        }

        if (preg_match('#^/blog(?:/page/\d+)?$#', $path) === 1) {
            return ['to' => '/journal', 'status' => 301];
        }

        $unpaginated = preg_replace('#/page/\d+$#', '', $path) ?? $path;

        if (preg_match('#^/category/(?:[^/]+/)*([^/]+)$#', $unpaginated, $matches) === 1) {
            $slug = $matches[1];

            return Category::query()->where('slug', $slug)->exists()
                ? ['to' => '/journal/category/'.$slug, 'status' => 301]
                : ['to' => '/journal', 'status' => 301];
        }

        if (preg_match('#^/tag/([^/]+)$#', $unpaginated, $matches) === 1) {
            return Tag::query()->where('slug', $matches[1])->exists()
                ? ['to' => '/journal/tag/'.$matches[1], 'status' => 301]
                : ['to' => null, 'status' => 410];
        }

        if (preg_match('#^/author(?:/.*)?$#', $path) === 1) {
            return ['to' => '/about', 'status' => 301];
        }

        return null;
    }
}
