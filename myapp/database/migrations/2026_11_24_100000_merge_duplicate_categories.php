<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Same name twice for one user: keep the oldest, point everything that used the copies at it, drop the copies. */
    public function up(): void
    {
        $keep = [];
        foreach (DB::table('categories')->orderBy('id')->get(['id', 'user_id', 'name']) as $c) {
            $key = $c->user_id.'|'.mb_strtolower(trim($c->name));
            if (! isset($keep[$key])) {
                $keep[$key] = $c->id;

                continue;
            }
            foreach (['transactions', 'recurring_items'] as $table) {
                DB::table($table)->where('category_id', $c->id)->update(['category_id' => $keep[$key]]);
            }
            DB::table('categories')->where('parent_id', $c->id)->update(['parent_id' => $keep[$key]]);
            DB::table('categories')->where('id', $c->id)->delete();
        }
    }

    public function down(): void
    {
        // merged rows cannot be told apart again
    }
};
