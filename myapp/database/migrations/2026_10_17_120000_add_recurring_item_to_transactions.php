<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // a payment made against a bill, subscription or loan; deleting the item keeps the history
        Schema::table('transactions', fn (Blueprint $t) => $t->foreignId('recurring_item_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurring_item_id');
        });
    }
};
