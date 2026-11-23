<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Cms\ClientAddressHasher;
use App\Infrastructure\Http\FormResponder;
use App\Infrastructure\Http\Requests\SubmitContactFormRequest;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Symfony\Component\HttpFoundation\Response;

/** POST /forms/contact: the contact form of the static site (docs/forms.md). */
final readonly class ContactFormController
{
    public function __invoke(SubmitContactFormRequest $request, ClientAddressHasher $hasher, FormResponder $responder): Response
    {
        $outcome = CommandBus::dispatch($request->toCommand($hasher->hash($request->ip())));

        return $responder->respond($request, $outcome);
    }
}
