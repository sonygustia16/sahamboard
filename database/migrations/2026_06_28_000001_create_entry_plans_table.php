<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_plans', function (Blueprint $table) {
            $table->id();
            $table->string('stock_code', 10);
            $table->decimal('entry_price', 15, 2)->default(0);
            $table->decimal('stop_loss', 15, 2)->default(0);
            $table->decimal('take_profit', 15, 2)->default(0);
            $table->date('plan_date')->nullable();
            $table->enum('status', ['active', 'entry_tercapai', 'stop_loss_tersentuh', 'closed'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_plans');
    }
};
