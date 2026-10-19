<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A chat gets a title from its first message; deleting one hides it from history but keeps the rows. */
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $t) {
            $t->string('title', 80)->nullable();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $t) {
            $t->dropColumn('title');
            $t->dropSoftDeletes();
        });
    }
};
