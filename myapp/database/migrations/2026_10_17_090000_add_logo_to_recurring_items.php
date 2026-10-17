<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ponytail: user-uploaded logo as a small data URI (browser resizes to 128px); move to the file disk if logos get big
        Schema::table('recurring_items', fn (Blueprint $t) => $t->text('logo')->nullable());
    }

    public function down(): void
    {
        Schema::table('recurring_items', fn (Blueprint $t) => $t->dropColumn('logo'));
    }
};
