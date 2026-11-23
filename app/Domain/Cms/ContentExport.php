<?php

declare(strict_types=1);

namespace App\Domain\Cms;

/**
 * Result of building the export: the contract payload, the media files it references and the problems that
 * would make the frontend schema (frontend/src/lib/cms/schema.ts) reject it.
 */
final readonly class ContentExport
{
    public const int SCHEMA_VERSION = 1;

    /** Same rule as the Zod `pageSchema.slug`. */
    public const string SLUG_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array{path: string, sha256: string}>  $files  public name => source file on the media disk
     * @param  list<string>  $problems
     */
    public function __construct(
        public array $payload,
        public array $files,
        public array $problems,
    ) {}
}
