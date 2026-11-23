<?php

declare(strict_types=1);

namespace App\Infrastructure\Models;

use App\Domain\Cms\PublishStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One start of the site build from the admin.
 *
 * @property int $id
 * @property int|null $user_id
 * @property PublishStatus $status
 * @property string|null $content_hash
 * @property string|null $jenkins_queue_url
 * @property string|null $jenkins_build_url
 * @property string|null $message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'status', 'content_hash', 'jenkins_queue_url', 'jenkins_build_url', 'message', 'started_at', 'finished_at'])]
class Publish extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PublishStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
