<?php

use App\Enums\CardType;
use App\Enums\RecurringKind;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $base = function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
        };

        Schema::create('income_sources', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('name');
            $t->bigInteger('amount');
            $t->string('currency')->default('PHP');
            $t->string('schedule')->default('monthly');
            $t->timestamps();
        });

        Schema::create('cards', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('name');
            $t->enum('type', array_column(CardType::cases(), 'value'));
            $t->bigInteger('balance')->default(0);
            $t->bigInteger('monthly_limit')->nullable();
            $t->string('currency')->default('PHP');
            $t->string('color')->nullable();
            $t->timestamps();
        });

        Schema::create('pods', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('name');
            $t->bigInteger('target_amount');
            $t->bigInteger('saved_amount')->default(0);
            $t->date('target_date')->nullable();
            $t->bigInteger('monthly_amount')->default(0);
            $t->string('currency')->default('PHP');
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('icon')->nullable();
            $t->timestamps();
        });

        Schema::create('transactions', function (Blueprint $t) use ($base) {
            $base($t);
            $t->foreignId('card_id')->constrained()->cascadeOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('type', array_column(TransactionType::cases(), 'value'));
            $t->bigInteger('amount');
            $t->string('currency')->default('PHP');
            $t->string('merchant')->nullable();
            $t->timestamp('occurred_at');
            $t->text('note')->nullable();
            $t->enum('source', array_column(TransactionSource::cases(), 'value'))->default('manual');
            $t->unsignedBigInteger('receipt_id')->nullable(); // FK is added with the receipts table (M3)
            $t->string('reference')->nullable();
            $t->timestamps();
        });

        Schema::create('recurring_items', function (Blueprint $t) use ($base) {
            $base($t);
            $t->string('name');
            $t->bigInteger('amount');
            $t->string('currency')->default('PHP');
            $t->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('cadence')->default('monthly');
            $t->date('next_due_on')->nullable();
            $t->enum('kind', array_column(RecurringKind::cases(), 'value'));
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['recurring_items', 'transactions', 'categories', 'pods', 'cards', 'income_sources'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
