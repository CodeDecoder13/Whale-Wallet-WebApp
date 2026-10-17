<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Received income is backed by a proof screenshot (a receipt with purpose=income) and its reference number. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', fn (Blueprint $t) => $t->string('purpose', 10)->default('expense'));

        Schema::table('income_sources', function (Blueprint $t) {
            $t->foreignId('receipt_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reference', 80)->nullable();
            $t->date('received_on')->nullable();
            $t->unique(['user_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('income_sources', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'reference']);
            $t->dropConstrainedForeignId('receipt_id');
            $t->dropColumn(['reference', 'received_on']);
        });
        Schema::table('receipts', fn (Blueprint $t) => $t->dropColumn('purpose'));
    }
};
