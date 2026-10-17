<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A transfer row keeps card_id for its card side; these record the other side so it can be reversed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->foreignId('to_card_id')->nullable()->constrained('cards')->nullOnDelete();
            $t->foreignId('to_pod_id')->nullable()->constrained('pods')->nullOnDelete();
            $t->foreignId('from_pod_id')->nullable()->constrained('pods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('to_card_id');
            $t->dropConstrainedForeignId('to_pod_id');
            $t->dropConstrainedForeignId('from_pod_id');
        });
    }
};
