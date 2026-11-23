<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

/**
 * Golden export: the real output of the exporter over sample data. The frontend builds its fixture from it
 * (frontend/scripts/build-fixture.mjs, `pnpm fixture:build`) and `pnpm fixture:check` verifies they agree, so
 * the Zod schema in the frontend CI validates what the backend really sends. After changing the exporter:
 *
 *   GOLDEN_UPDATE=1 php artisan test --filter=GoldenExportTest
 *
 * and then `pnpm fixture:build` in frontend/.
 */
final class GoldenExportTest extends TestCase
{
    private const string GOLDEN = __DIR__ . '/../../Fixtures/content-export.golden.json';

    public function test_it_matches_the_committed_golden_export(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        $this->travelTo('2026-11-18 08:00:00');
        SampleContent::create();

        $export = QueryBus::execute(new ExportContentQuery());

        self::assertSame([], $export->problems);

        $golden = json_encode(['export' => $export->payload], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        if (filter_var(getenv('GOLDEN_UPDATE'), FILTER_VALIDATE_BOOLEAN)) {
            file_put_contents(self::GOLDEN, $golden);
        }

        self::assertFileExists(self::GOLDEN, 'The golden export is missing, generate it with GOLDEN_UPDATE=1.');
        self::assertSame(file_get_contents(self::GOLDEN), $golden);
    }

    public function test_the_hash_does_not_depend_on_the_generation_time(): void
    {
        Storage::fake(PublicMediaStore::MEDIA_DISK);
        SampleContent::create();

        $this->travelTo('2026-11-18 08:00:00');
        $first = QueryBus::execute(new ExportContentQuery())->payload;
        $this->travelTo('2026-11-19 09:30:00');
        $second = QueryBus::execute(new ExportContentQuery())->payload;

        self::assertNotSame($first['generatedAt'], $second['generatedAt']);
        self::assertSame($first['contentHash'], $second['contentHash']);
    }
}
