<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_filters', function (Blueprint $table) {
            // Preset sebelumnya cuma nyimpen previous/frequency/value — filter
            // "Non-Regular Value" di form pencarian belum ikut kesimpen sama sekali.
            $table->string('op_non_regular_value', 2)->default('=')->after('value');
            $table->unsignedBigInteger('non_regular_value')->nullable()->after('op_non_regular_value');
        });
    }

    public function down(): void
    {
        Schema::table('saved_filters', function (Blueprint $table) {
            $table->dropColumn(['op_non_regular_value', 'non_regular_value']);
        });
    }
};
