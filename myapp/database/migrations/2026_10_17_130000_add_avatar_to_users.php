<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ponytail: a 128px JPEG data URI (about 5KB) because auth.user ships on every page; use the file disk if avatars grow
        Schema::table('users', fn (Blueprint $t) => $t->text('avatar')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('avatar'));
    }
};
