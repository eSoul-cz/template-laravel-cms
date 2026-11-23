<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Application\Cms\ContentChanges;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Site-wide settings: a single row, read through {@see self::current()}.
 *
 * @property int $id
 * @property string $site_name
 * @property string|null $description
 * @property string|null $contact_email
 * @property string|null $footer_text
 * @property string|null $og_image path on the cms-media disk
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['site_name', 'description', 'contact_email', 'footer_text', 'og_image'])]
class SiteSetting extends Model
{
    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create(['site_name' => config()->string('app.name')]);
    }

    protected static function booted(): void
    {
        static::saved(static fn () => ContentChanges::touch());
    }
}
