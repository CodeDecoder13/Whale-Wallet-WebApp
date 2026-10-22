<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A bill, subscription or loan can be hidden for now: it stays on file but stops counting everywhere until shown again. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->timestamp('hidden_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropColumn('hidden_at'));
    }
};
