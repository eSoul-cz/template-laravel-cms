<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Deterministic content for the golden export and the endpoint tests. Needs `Storage::fake(PublicMediaStore::MEDIA_DISK)`.
 *
 * The pages have different `updated_at` dates (the `updatedAt` of the export, the `lastmod` of the sitemap) and cover the
 * optional contract fields: a page with its own social image, a `noindex` page and an unpublished one that is not exported.
 */
final class SampleContent
{
    public static function create(): void
    {
        Storage::disk(PublicMediaStore::MEDIA_DISK)->put('images/pixel.png', (string) file_get_contents(__DIR__ . '/../Fixtures/pixel.png'));

        SiteSetting::query()->create([
            'site_name' => 'Example Site',
            'description' => 'Example Site is a sample website built from the headless CMS export: pages, one settings record and a verified static build.',
            'contact_email' => 'hello@example.com',
            'footer_text' => 'Example Site s.r.o.',
            'og_image' => 'images/pixel.png',
        ]);

        self::page('2026-11-10 09:00:00', [
            'slug' => 'home',
            'title' => 'Welcome',
            'description' => 'The home page of the example site, rendered from the CMS export with a heading, two paragraphs of text and a sample image.',
            'is_home' => true,
            'sort' => 10,
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '2', 'text' => 'What we do']],
                ['type' => 'text', 'data' => ['body' => "First paragraph.\n\nSecond paragraph."]],
                ['type' => 'image', 'data' => ['image' => 'images/pixel.png', 'alt' => 'A blue gradient', 'caption' => 'A tiny sample image']],
            ],
        ]);

        self::page('2026-11-12 10:30:00', [
            'slug' => 'about',
            'title' => 'About us',
            'sort' => 20,
            'og_image' => 'images/pixel.png',
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '3', 'text' => 'Our story']],
                ['type' => 'text', 'data' => ['body' => 'We write small, boring, reliable software.']],
            ],
        ]);

        // Exported, but kept out of the sitemap, llms.txt and the Markdown versions.
        self::page('2026-11-15 12:00:00', [
            'slug' => 'thanks',
            'title' => 'Thank you',
            'sort' => 25,
            'noindex' => true,
            'blocks' => [['type' => 'text', 'data' => ['body' => 'Your message was sent.']]],
        ]);

        // Not exported: unpublished.
        self::page('2026-11-16 08:00:00', ['slug' => 'draft', 'title' => 'Draft', 'is_published' => false, 'sort' => 30, 'blocks' => []]);
    }

    /** @param  array<string, mixed>  $attributes */
    private static function page(string $updatedAt, array $attributes): void
    {
        // A date set by hand survives the save (Eloquent only fills `updated_at` when it is not dirty).
        $page = new Page($attributes);
        $page->updated_at = Carbon::parse($updatedAt);
        $page->save();
    }
}
