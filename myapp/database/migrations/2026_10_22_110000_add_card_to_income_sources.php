<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The card that received the money; its balance goes up when income is recorded and back down when it is removed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->foreignId('card_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('income_sources', fn (Blueprint $t) => $t->dropConstrainedForeignId('card_id'));
    }
};
