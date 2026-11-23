<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\SiteSetting;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class ContentExporterTest extends TestCase
{
    public function test_an_empty_site_cannot_be_exported(): void
    {
        $problems = QueryBus::execute(new ExportContentQuery())->problems;

        self::assertContains('There is no published page.', $problems);
        self::assertContains('Exactly one published page must be the home page.', $problems);
    }

    public function test_only_published_pages_are_exported_in_order(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();

        $pages = QueryBus::execute(new ExportContentQuery())->payload['pages'];

        self::assertIsArray($pages);
        self::assertSame(['home', 'about', 'thanks'], array_column($pages, 'slug'));
    }

    public function test_pages_carry_the_optional_contract_fields(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();

        $pages = QueryBus::execute(new ExportContentQuery())->payload['pages'];

        self::assertIsArray($pages);
        self::assertSame(
            ['2026-11-10T09:00:00+00:00', '2026-11-12T10:30:00+00:00', '2026-11-15T12:00:00+00:00'],
            array_column($pages, 'updatedAt'),
            'updatedAt is the date the page itself was saved, not the time of the export',
        );
        self::assertSame([false, false, true], array_column($pages, 'noindex'));
        self::assertNull($pages[0]['ogImage'], 'a page without an image of its own has none: the frontend falls back to the site image');
        self::assertIsArray($pages[1]['ogImage']);
        self::assertMatchesRegularExpression('#^/_cms/[a-f0-9]{20}\.png$#', (string) $pages[1]['ogImage']['url']);
    }

    public function test_saving_a_page_moves_only_its_own_updated_at(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();

        $this->travelTo('2026-12-01 07:00:00');
        Page::query()->where('slug', 'about')->firstOrFail()->update(['title' => 'About the company']);

        $pages = QueryBus::execute(new ExportContentQuery())->payload['pages'];

        self::assertIsArray($pages);
        self::assertSame(['2026-11-10T09:00:00+00:00', '2026-12-01T07:00:00+00:00', '2026-11-15T12:00:00+00:00'], array_column($pages, 'updatedAt'));
    }

    public function test_marking_a_page_as_home_unsets_the_previous_home(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();

        Page::query()->where('slug', 'about')->firstOrFail()->update(['is_home' => true]);

        self::assertSame(['about'], Page::query()->where('is_home', true)->pluck('slug')->all());
    }

    public function test_every_contract_violation_is_reported(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        Storage::disk(PublicMediaStore::MEDIA_DISK)->put('images/page.html', '<script>alert(1)</script>');

        SiteSetting::query()->create(['site_name' => 'Example', 'contact_email' => 'not-an-email', 'og_image' => 'images/missing.png']);
        Page::query()->create([
            'slug' => 'Bad Slug',
            'title' => 'Broken',
            'is_home' => true,
            'blocks' => [
                ['type' => 'heading', 'data' => ['level' => '2', 'text' => '']],
                ['type' => 'image', 'data' => ['image' => 'images/page.html', 'alt' => '']],
                ['type' => 'carousel', 'data' => []],
            ],
        ]);

        $problems = implode("\n", QueryBus::execute(new ExportContentQuery())->problems);

        foreach (['contact e-mail', 'images/missing.png does not exist', 'single hyphens', 'heading is empty', 'file type text/', 'no alt text', 'unknown block type'] as $expected) {
            self::assertStringContainsString($expected, $problems);
        }
    }
}
