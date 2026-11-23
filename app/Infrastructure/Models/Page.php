<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Application\Cms\ContentChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A page of the static site. The home page is rendered at `/`, the others at `/<slug>/`.
 *
 * `blocks` is the state of the Filament Builder field: a list of `{type, data}` items with the types
 * `heading` (level, text), `text` (body) and `image` (image path on the cms-media disk, alt, caption).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property bool $is_home
 * @property bool $is_published
 * @property int $sort
 * @property bool $noindex page stays out of the sitemap, llms.txt and the Markdown versions of the site
 * @property string|null $og_image path on the cms-media disk; the site image is used when empty
 * @property array<array-key, mixed>|null $blocks
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'title', 'description', 'is_home', 'is_published', 'sort', 'noindex', 'og_image', 'blocks'])]
class Page extends Model
{
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    protected static function booted(): void
    {
        // One home page: marking this one unsets the others (query update, no events).
        static::saved(static function (self $page): void {
            if ($page->is_home) {
                self::query()->whereKeyNot($page->getKey())->where('is_home', true)->update(['is_home' => false]);
            }

            ContentChanges::touch();
        });
        static::deleted(static fn () => ContentChanges::touch());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_home' => 'boolean',
            'is_published' => 'boolean',
            'sort' => 'integer',
            'noindex' => 'boolean',
            'blocks' => 'array',
        ];
    }
}
