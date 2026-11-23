<?php

declare(strict_types=1);

namespace App\Infrastructure\Cms;

/**
 * The sites that may post to the form endpoint (`cms.forms.allowed_origins`). An empty list rejects every
 * origin, except in a local environment where any origin is allowed, so the form works against a dev server.
 */
final readonly class FormOrigins
{
    /**
     * The allowed origins as `scheme://host[:port]`, lower case, without a trailing slash.
     *
     * @return list<string>
     */
    public function allowed(): array
    {
        $origins = [];

        foreach (config()->array('cms.forms.allowed_origins') as $configured) {
            $origin = is_string($configured) ? $this->originOf($configured) : null;

            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * Host names of the allowed origins, for the hostname the reCAPTCHA answer reports.
     *
     * @return list<string>
     */
    public function hosts(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $origin): string => (string) parse_url($origin, PHP_URL_HOST),
            $this->allowed(),
        )));
    }

    /** Whether a request from `$origin` may use the endpoint; `$origin` is an origin or any URL on it. */
    public function permits(?string $origin): bool
    {
        if ($this->allowed() === []) {
            return app()->isLocal();
        }

        $origin = $origin === null ? null : $this->originOf($origin);

        return $origin !== null && in_array($origin, $this->allowed(), true);
    }

    /** `scheme://host[:port]` of an absolute http(s) URL, null for anything else (relative URLs, `null`, other schemes). */
    public function originOf(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);

        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        return $scheme . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
