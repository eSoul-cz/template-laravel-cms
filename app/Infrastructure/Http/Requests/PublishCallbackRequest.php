<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Requests;

use App\Domain\CQRS\Command\Cms\FinishPublishCommand;
use App\Infrastructure\Cms\JenkinsClient;
use App\Infrastructure\Models\Publish;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /cms-api/v1/publishes/{publish}/status: the result Jenkins reports after the site build.
 *
 * Body: {"result": "SUCCESS"|"UNSTABLE"|"FAILURE"|"ABORTED"|…, "buildUrl": "…", "contentHash": "sha256:…", "message": "…"}
 * `UNSTABLE` deployed the site (a warning only): success with a note in `message`.
 *
 * @property-read string $result
 * @property-read string|null $buildUrl
 * @property-read string|null $contentHash
 * @property-read string|null $message
 */
final class PublishCallbackRequest extends FormRequest
{
    /** The caller is authenticated by the `AuthenticateCmsToken` middleware of the route, there is no user. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'result' => ['required', 'string', 'max:20'],
            'buildUrl' => ['nullable', 'url', 'max:255'],
            'contentHash' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toCommand(Publish $publish): FinishPublishCommand
    {
        $result = $this->string('result')->toString();
        $message = $this->filled('message') ? $this->string('message')->toString() : null;

        if ($result === 'UNSTABLE') {
            $message = trim('The build was unstable but the site was deployed. ' . $message);
        }

        return new FinishPublishCommand(
            publishId: $publish->id,
            success: in_array($result, JenkinsClient::DEPLOYED_RESULTS, true),
            buildUrl: $this->filled('buildUrl') ? $this->string('buildUrl')->toString() : null,
            contentHash: $this->filled('contentHash') ? $this->string('contentHash')->toString() : null,
            message: $message,
        );
    }
}
