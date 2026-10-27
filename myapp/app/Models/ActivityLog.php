<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One thing a user did (sign-in, scan, reset...), shown in the admin feed; pruned after 90 days. */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Never throws: a logging problem must not break what the user was doing. */
    public static function record(string $event, ?int $userId, array $meta = []): void
    {
        try {
            static::create(['event' => $event, 'user_id' => $userId, 'meta' => $meta ?: null, 'ip' => request()?->ip(), 'created_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
