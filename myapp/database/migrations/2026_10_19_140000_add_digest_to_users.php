<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The daily Whale tips run at digest_time (Philippine time); the email only goes to users who opted in. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('digest_enabled')->default(false);
            $t->string('digest_time', 5)->default('09:00');
            $t->date('digest_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['digest_enabled', 'digest_time', 'digest_sent_on']));
    }
};
