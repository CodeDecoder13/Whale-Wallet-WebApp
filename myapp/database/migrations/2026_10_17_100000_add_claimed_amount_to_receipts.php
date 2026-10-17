<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // income proofs only: the amount the user told Whale Chat, checked against what the screenshot reads
        Schema::table('receipts', fn (Blueprint $t) => $t->bigInteger('claimed_amount')->nullable());
    }

    public function down(): void
    {
        Schema::table('receipts', fn (Blueprint $t) => $t->dropColumn('claimed_amount'));
    }
};
