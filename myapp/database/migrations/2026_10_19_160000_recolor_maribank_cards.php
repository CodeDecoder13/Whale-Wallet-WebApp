<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** MariBank's brand colour is orange; cards saved with the old purple preset follow it. */
    public function up(): void
    {
        DB::table('cards')->where('color', '#7B3FF2')->update(['color' => '#F26B21']);
    }

    public function down(): void
    {
        DB::table('cards')->where('color', '#F26B21')->where('name', 'like', '%mari%')->update(['color' => '#7B3FF2']);
    }
};
