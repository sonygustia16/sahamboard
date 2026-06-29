<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('money_management_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_capital', 18, 2)->default(0);
            $table->decimal('max_risk_per_stock', 5, 2)->default(0); // dalam persen
            $table->unsignedInteger('max_positions')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_management_settings');
    }
};
