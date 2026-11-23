<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Domain\CQRS\Command\Cms\FinishPublishCommand;
use App\Infrastructure\Cms\JenkinsClient;
use App\Infrastructure\Models\Publish;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /cms-api/v1/publishes/{publish}/status: Jenkins reports the result after the site build.
 *
 * Body: {"result": "SUCCESS"|"UNSTABLE"|"FAILURE"|"ABORTED"|…, "buildUrl": "…", "contentHash": "sha256:…", "message": "…"}
 * `UNSTABLE` deployed the site (a warning only): success with a note in `message`.
 */
final readonly class PublishCallbackController
{
    public function __invoke(Request $request, Publish $publish): JsonResponse
    {
        $request->validate([
            'result' => ['required', 'string', 'max:20'],
            'buildUrl' => ['nullable', 'url', 'max:255'],
            'contentHash' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $request->string('result')->toString();
        $message = $request->filled('message') ? $request->string('message')->toString() : null;

        if ($result === 'UNSTABLE') {
            $message = trim('The build was unstable but the site was deployed. ' . $message);
        }

        $outcome = CommandBus::dispatch(new FinishPublishCommand(
            publishId: $publish->id,
            success: in_array($result, JenkinsClient::DEPLOYED_RESULTS, true),
            buildUrl: $request->filled('buildUrl') ? $request->string('buildUrl')->toString() : null,
            contentHash: $request->filled('contentHash') ? $request->string('contentHash')->toString() : null,
            message: $message,
        ));

        return response()->json(['status' => $outcome->status->value]);
    }
}
