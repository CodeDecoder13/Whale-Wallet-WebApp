<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->index(['user_id', 'occurred_at']);
            $t->index(['card_id', 'type', 'occurred_at']);
        });
        Schema::table('recurring_items', fn (Blueprint $t) => $t->index(['user_id', 'next_due_on']));

        // a push token belongs to one account: keep the newest row per token, then make the token unique on its own
        DB::table('devices')->whereNotIn('id', DB::table('devices')->selectRaw('max(id)')->groupBy('expo_push_token'))->delete();
        Schema::table('devices', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'expo_push_token']);
            $t->unique('expo_push_token');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->dropUnique(['expo_push_token']);
            $t->unique(['user_id', 'expo_push_token']);
        });
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropIndex(['user_id', 'next_due_on']));
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropIndex(['card_id', 'type', 'occurred_at']);
            $t->dropIndex(['user_id', 'occurred_at']);
        });
    }
};
