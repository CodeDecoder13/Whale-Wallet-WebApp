<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // loans only: how many monthly payments, counted from next_due_on; null = repeats forever
        Schema::table('recurring_items', fn (Blueprint $t) => $t->unsignedSmallInteger('installments')->nullable());
    }

    public function down(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropColumn('installments'));
    }
};
