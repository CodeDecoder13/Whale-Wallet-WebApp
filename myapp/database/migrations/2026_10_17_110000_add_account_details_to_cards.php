<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // so a card can be shared for transfers: holder name, account number (stored encrypted) and the bank's QR
        Schema::table('cards', function (Blueprint $t) {
            $t->string('account_name')->nullable();
            $t->text('account_number')->nullable();
            $t->text('qr')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $t) => $t->dropColumn(['account_name', 'account_number', 'qr']));
    }
};
