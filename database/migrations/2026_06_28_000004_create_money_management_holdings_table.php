<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_management_holdings', function (Blueprint $table) {
            $table->id();
            $table->string('stock_code', 10);
            $table->decimal('allocation', 18, 2)->default(0);
            $table->decimal('pnl', 18, 2)->default(0); // unrealized profit/loss, diisi manual untuk sekarang
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_management_holdings');
    }
};
