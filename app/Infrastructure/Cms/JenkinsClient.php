<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

use App\Application\Contract\Cms\BuildTrigger;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Starts and watches the Jenkins job that builds and deploys the static site (frontend/ of the monorepo).
 * Authenticates with a user API token, which needs no CSRF crumb.
 *
 * Jenkins answers are validated: an unexpected shape (login page of a proxy, `null`, a list) is a protocol error,
 * not "queued" or "failed". Credentials go only to the origin of `cms.jenkins.job_url`.
 */
final class JenkinsClient implements BuildTrigger
{
    /**
     * Build results after which the site is deployed. `UNSTABLE` = deployed with a warning (e.g. a dependency
     * audit that only warns on the main branch).
     */
    public const array DEPLOYED_RESULTS = ['SUCCESS', 'UNSTABLE'];

    public function isConfigured(): bool
    {
        return filled(config('cms.jenkins.job_url'))
            && filled(config('cms.jenkins.user'))
            && filled(config('cms.jenkins.api_token'));
    }

    /**
     * @param  array<string, string>  $parameters
     * @return string URL of the Jenkins queue item
     */
    public function triggerBuild(array $parameters): string
    {
        $response = $this->request()
            ->asForm()
            ->post(rtrim($this->jobUrl(), '/') . '/buildWithParameters', $parameters);

        if ( ! $response->successful()) {
            throw new RuntimeException("Jenkins responded with HTTP {$response->status()}.");
        }

        $location = (string) $response->header('Location');

        if ($location === '') {
            throw new RuntimeException('Jenkins did not return a queue item location.');
        }

        return $this->queueItemUrl($location);
    }

    /**
     * Poll fallback for when the callback of the build did not arrive: queue item first, then the build.
     *
     * @return array{state: 'queued'|'running'|'success'|'failed', build_url: string|null}
     *
     * @throws RuntimeException Jenkins answered differently from what its API describes
     */
    public function buildStatus(string $queueUrl, ?string $buildUrl): array
    {
        if ($buildUrl === null) {
            $queue = $this->fetch($queueUrl, 'queue item');
            $cancelled = array_key_exists('cancelled', $queue) ? $queue['cancelled'] : false;

            if ( ! is_bool($cancelled)) {
                throw $this->unexpected('queue item: cancelled');
            }

            if ($cancelled) {
                return ['state' => 'failed', 'build_url' => null];
            }

            $executable = $queue['executable'] ?? null;

            if ($executable === null) {
                return ['state' => 'queued', 'build_url' => null];
            }

            $buildUrl = is_array($executable) ? ($executable['url'] ?? null) : null;

            if ( ! is_string($buildUrl) || $buildUrl === '') {
                throw $this->unexpected('queue item: executable.url');
            }
        }

        $build = $this->fetch(rtrim($buildUrl, '/'), 'build');
        $building = $build['building'] ?? null;
        $result = $build['result'] ?? null;

        if ( ! is_bool($building) || ($result !== null && ! is_string($result))) {
            throw $this->unexpected('build: building/result');
        }

        // `building: false` without a result: Jenkins is finishing the build.
        if ($building || $result === null) {
            return ['state' => 'running', 'build_url' => $buildUrl];
        }

        return [
            'state' => in_array($result, self::DEPLOYED_RESULTS, true) ? 'success' : 'failed',
            'build_url' => $buildUrl,
        ];
    }

    /**
     * `{url}/api/json` as a JSON object; nothing is sent to an origin other than `job_url`.
     *
     * @return array<array-key, mixed>
     */
    private function fetch(string $url, string $what): array
    {
        if ( ! $this->isJenkinsUrl($url)) {
            throw $this->unexpected("{$what} on another host");
        }

        $body = json_decode($this->request()->get("{$url}/api/json")->throw()->body(), true);

        if ( ! is_array($body) || array_is_list($body) && $body !== []) {
            throw $this->unexpected("{$what} is not a JSON object");
        }

        return $body;
    }

    private function jobUrl(): string
    {
        $url = config('cms.jenkins.job_url');

        return is_string($url) ? $url : '';
    }

    /** Same scheme, host and port as `cms.jenkins.job_url`. */
    private function isJenkinsUrl(string $url): bool
    {
        $jenkins = $this->origin($this->jobUrl());

        return $jenkins !== null && $this->origin($url) === $jenkins;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if ( ! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * Address of the queue item on the origin of `cms.jenkins.job_url`. Only the path of `Location` is used:
     * behind a proxy Jenkins builds scheme, host and port from the request as it sees it (relative, `http://…`,
     * an internal address), and credentials go only to the origin of the job. The path must be a queue item.
     */
    private function queueItemUrl(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);
        $job = parse_url($this->jobUrl());

        if ( ! is_string($path) || preg_match('#^(/[^?\#]*)?/queue/item/\d+/?$#', $path) !== 1 || ! isset($job['scheme'], $job['host'])) {
            throw $this->unexpected('queue item location');
        }

        $port = isset($job['port']) ? ":{$job['port']}" : '';

        return "{$job['scheme']}://{$job['host']}{$port}" . rtrim($path, '/');
    }

    /** No response body and no credentials: the message goes to the admin. */
    private function unexpected(string $detail): RuntimeException
    {
        return new RuntimeException("Unexpected Jenkins response ({$detail}).");
    }

    /** Redirects are not followed: the origin is checked only for the address the request is sent to. */
    private function request(): PendingRequest
    {
        $user = config('cms.jenkins.user');
        $token = config('cms.jenkins.api_token');

        return Http::withBasicAuth(is_string($user) ? $user : '', is_string($token) ? $token : '')
            ->acceptJson()
            ->withoutRedirecting()
            ->timeout(15);
    }
}
