<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('admin_role', 16)->nullable()->index(); // owner or support; null for everyone else
            $t->timestamp('suspended_at')->nullable();
        });

        // what users do (sign-ins, scans, resets...), newest first in the admin feed
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('event', 40)->index();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        // what admins do, and every look at a user's data; rows are never edited
        Schema::create('admin_audit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('admin_id')->index();
            $t->string('action', 60);
            $t->foreignId('target_user_id')->nullable()->index();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit');
        Schema::dropIfExists('activity_logs');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['admin_role', 'suspended_at']));
    }
};
