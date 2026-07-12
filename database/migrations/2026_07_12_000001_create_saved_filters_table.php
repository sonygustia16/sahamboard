<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_filters', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nama preset, misal "Saham likuid tinggi"
            $table->string('op_previous', 2)->default('=');
            $table->unsignedBigInteger('previous')->nullable();
            $table->string('op_frequency', 2)->default('=');
            $table->unsignedBigInteger('frequency')->nullable();
            $table->string('op_value', 2)->default('=');
            $table->unsignedBigInteger('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_filters');
    }
};
