<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\CQRS\Query\Cms\ExportContentQuery;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Http\JsonResponse;

/**
 * GET /cms-api/v1/export: content for the build of the static site. The media files the export references are on
 * /_cms/ when the response is sent: the build does not download them, it only checks that the web server serves them.
 */
final readonly class ExportController
{
    public function __invoke(PublicMediaStore $media): JsonResponse
    {
        $export = QueryBus::execute(new ExportContentQuery());

        // The same rules as the frontend schema: better a clear 422 here than a failing build.
        if ($export->problems !== []) {
            return response()->json(['message' => 'The content does not satisfy the export contract.', 'problems' => $export->problems], 422);
        }

        // A write failure is a 500 and the site build fails: the site must not link a file that does not exist.
        $media->store($export->files);

        return response()->json($export->payload, options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
