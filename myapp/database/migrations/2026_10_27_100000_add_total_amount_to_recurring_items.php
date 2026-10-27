<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // a loan's full amount in centavos; amount stays the payment each time
    public function up(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->unsignedBigInteger('total_amount')->nullable());
    }

    public function down(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropColumn('total_amount'));
    }
};
