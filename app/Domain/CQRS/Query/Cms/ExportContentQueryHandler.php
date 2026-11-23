<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\ContentExport;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\SiteSetting;
use Esoul\Cqrs\Attributes\HandlesQuery;
use Esoul\Cqrs\Contracts\QueryHandlerInterface;
use Esoul\Cqrs\Contracts\QueryInterface;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Export contract v1 for the build of the static site (docs/contract.md).
 *
 * Only published pages, ordered by `sort` and `id`. The handler owns the invariants of the contract: while it
 * builds the payload it reports everything the Zod schema of the frontend (frontend/src/lib/cms/schema.ts)
 * would reject. A publish asks for `problems` and has no rules of its own; Zod stays as the second check
 * during the site build.
 */
#[HandlesQuery(ExportContentQuery::class)]
final class ExportContentQueryHandler implements QueryHandlerInterface
{
    /** sha256 of empty content: a failed upload must never reach the site. */
    private const string EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /** @var list<string> */
    private array $problems = [];

    /** @var array<string, array{path: string, sha256: string}> */
    private array $files = [];

    public function __construct(private readonly PublicMediaStore $media) {}

    public function handle(QueryInterface $query): ContentExport
    {
        if ( ! $query instanceof ExportContentQuery) {
            throw new LogicException('Unsupported query: ' . $query::class);
        }

        $this->problems = [];
        $this->files = [];

        $pages = Page::query()->published()->orderBy('sort')->orderBy('id')->get();

        if ($pages->isEmpty()) {
            $this->problems[] = 'There is no published page.';
        }

        if ($pages->where('is_home', true)->count() !== 1) {
            $this->problems[] = 'Exactly one published page must be the home page.';
        }

        $payload = [
            'schemaVersion' => ContentExport::SCHEMA_VERSION,
            'settings' => $this->settings(SiteSetting::current()),
            'pages' => $pages->map(fn (Page $page): array => $this->page($page))->all(),
        ];

        // The hash covers the content only, so two exports of the same content have the same hash.
        $hash = 'sha256:' . hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return new ContentExport(
            payload: ['schemaVersion' => ContentExport::SCHEMA_VERSION, 'generatedAt' => now()->toIso8601String(), 'contentHash' => $hash] + $payload,
            files: $this->files,
            problems: $this->problems,
        );
    }

    /** @return array<string, mixed> */
    private function settings(SiteSetting $settings): array
    {
        if (trim($settings->site_name) === '') {
            $this->problems[] = 'Site settings: the site name is empty.';
        }

        if ($settings->contact_email !== null && $settings->contact_email !== '' && filter_var($settings->contact_email, FILTER_VALIDATE_EMAIL) === false) {
            $this->problems[] = 'Site settings: the contact e-mail is not a valid address.';
        }

        return [
            'siteName' => $settings->site_name,
            'description' => $this->nullable($settings->description),
            'contactEmail' => $this->nullable($settings->contact_email),
            'footerText' => $this->nullable($settings->footer_text),
            'ogImage' => $this->image($settings->og_image, 'Site settings: the social image'),
        ];
    }

    /** @return array<string, mixed> */
    private function page(Page $page): array
    {
        $label = "Page \"{$page->slug}\"";

        if (preg_match(ContentExport::SLUG_PATTERN, $page->slug) !== 1) {
            $this->problems[] = "{$label}: the slug may contain only a-z, 0-9 and single hyphens.";
        }

        if (trim($page->title) === '') {
            $this->problems[] = "{$label}: the title is empty.";
        }

        $blocks = [];

        foreach (array_values($page->blocks ?? []) as $index => $block) {
            $exported = is_array($block) ? $this->block($block, $label . ', block ' . ($index + 1)) : null;

            if ($exported !== null) {
                $blocks[] = $exported;
            }
        }

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'isHome' => $page->is_home,
            'sort' => $page->sort,
            'title' => $page->title,
            'description' => $this->nullable($page->description),
            'blocks' => $blocks,
            // Optional fields of contract v1: when the content last changed (sitemap lastmod), noindex, own Open Graph image.
            'updatedAt' => $page->updated_at?->toIso8601String(),
            'noindex' => $page->noindex,
            'ogImage' => $this->image($page->og_image, "{$label}: the social image"),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $block  `{type, data}` item of the Builder field
     * @return array<string, mixed>|null
     */
    private function block(array $block, string $label): ?array
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        switch ($block['type'] ?? null) {
            case 'heading':
                $text = $this->text($data['text'] ?? null);
                $level = (int) (is_scalar($data['level'] ?? null) ? $data['level'] : 2);

                if ($text === null) {
                    $this->problems[] = "{$label}: the heading is empty.";
                }

                return $text === null ? null : ['type' => 'heading', 'level' => $level === 3 ? 3 : 2, 'text' => $text];

            case 'text':
                $body = $this->text($data['body'] ?? null);

                if ($body === null) {
                    $this->problems[] = "{$label}: the text is empty.";
                }

                return $body === null ? null : ['type' => 'text', 'body' => $body];

            case 'image':
                $path = is_string($data['image'] ?? null) ? $data['image'] : null;
                $image = $this->image($path, "{$label}: the image");
                $alt = $this->text($data['alt'] ?? null);

                if ($path === null || $path === '') {
                    $this->problems[] = "{$label}: the image is missing.";
                }

                if ($alt === null) {
                    $this->problems[] = "{$label}: the image has no alt text.";
                }

                return $image === null || $alt === null
                    ? null
                    : ['type' => 'image', 'image' => $image, 'alt' => $alt, 'caption' => $this->text($data['caption'] ?? null)];

            default:
                $this->problems[] = "{$label}: unknown block type.";

                return null;
        }
    }

    /**
     * Image on the media disk -> file object of the contract. The file is copied to the public disk by
     * {@see PublicMediaStore::store()} when the export is served.
     *
     * @return array{url: string, mime: string, bytes: int, sha256: string, width: int|null, height: int|null}|null
     */
    private function image(?string $path, string $label): ?array
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk(PublicMediaStore::MEDIA_DISK);

        if ( ! $disk->exists($path)) {
            $this->problems[] = "{$label}: the file {$path} does not exist.";

            return null;
        }

        $absolute = $disk->path($path);
        $sha256 = hash_file('sha256', $absolute);
        $mime = mime_content_type($absolute);
        $bytes = filesize($absolute);

        if ($sha256 === false || $mime === false || $bytes === false || $sha256 === self::EMPTY_SHA256) {
            $this->problems[] = "{$label}: the file {$path} is empty or unreadable, upload it again.";

            return null;
        }

        $name = $this->media->name($sha256, $mime);

        if ($name === null) {
            $this->problems[] = "{$label}: the file type {$mime} is not allowed (webp, png, jpeg, avif).";

            return null;
        }

        $this->files[$name] = ['path' => $absolute, 'sha256' => $sha256];
        $size = getimagesize($absolute);

        return [
            'url' => PublicMediaStore::URL_PREFIX . $name,
            'mime' => $mime,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'width' => $size === false ? null : $size[0],
            'height' => $size === false ? null : $size[1],
        ];
    }

    private function nullable(?string $value): ?string
    {
        return $this->text($value);
    }

    private function text(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
