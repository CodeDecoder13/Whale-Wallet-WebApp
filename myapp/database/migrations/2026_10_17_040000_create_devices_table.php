<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('expo_push_token');
            $t->string('platform', 20);
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'expo_push_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
