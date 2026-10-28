<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // when an admin last passed the PIN + emailed-code gate, shown on the Admins page
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('last_admin_gate_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('last_admin_gate_at'));
    }
};
