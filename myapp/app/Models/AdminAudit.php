<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What an admin did or looked at. Rows are only ever added. */
class AdminAudit extends Model
{
    protected $table = 'admin_audit';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public static function record(User $admin, string $action, ?User $target = null, array $meta = []): void
    {
        static::create([
            'admin_id' => $admin->id, 'action' => $action, 'target_user_id' => $target?->id,
            'meta' => $meta ?: null, 'ip' => request()?->ip(), 'created_at' => now(),
        ]);
    }
}
