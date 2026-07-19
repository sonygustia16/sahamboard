<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            // Fee beli/jual beda-beda tiap sekuritas, jadi diinput manual per saham (persen)
            $table->decimal('fee_beli_pct', 5, 3)->nullable()->after('entry_lot');
            $table->decimal('fee_jual_pct', 5, 3)->nullable()->after('fee_beli_pct');
        });
    }

    public function down(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            $table->dropColumn(['fee_beli_pct', 'fee_jual_pct']);
        });
    }
};
