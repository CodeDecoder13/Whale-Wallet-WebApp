<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** salary replaces the expected baseline for its month; side (extra) income is added on top of it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->string('kind', 10)->default('salary'));
    }

    public function down(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->dropColumn('kind'));
    }
};
