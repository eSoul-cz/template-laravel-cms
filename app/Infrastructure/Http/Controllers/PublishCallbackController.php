<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Requests\PublishCallbackRequest;
use App\Infrastructure\Models\Publish;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Illuminate\Http\JsonResponse;

/** POST /cms-api/v1/publishes/{publish}/status: Jenkins reports the result after the site build (body in {@see PublishCallbackRequest}). */
final readonly class PublishCallbackController
{
    public function __invoke(PublishCallbackRequest $request, Publish $publish): JsonResponse
    {
        $outcome = CommandBus::dispatch($request->toCommand($publish));

        return response()->json(['status' => $outcome->status->value]);
    }
}
