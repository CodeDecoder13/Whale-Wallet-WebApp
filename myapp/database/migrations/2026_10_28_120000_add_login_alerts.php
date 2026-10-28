<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // an owner's choice: email me on every user sign-in ('all'), only from a device not seen before ('new'), or never ('off')
        Schema::table('users', fn (Blueprint $t) => $t->string('login_alerts', 3)->default('all'));

        // the browsers and devices each user has signed in from, to tell "new device" from a usual one
        Schema::create('login_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('ua_hash', 40);
            $t->string('label', 80);
            $t->string('last_ip', 45)->nullable();
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->unique(['user_id', 'ua_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_devices');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('login_alerts'));
    }
};
