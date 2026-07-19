<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            // Entry = harga Close saat saham di-star dari tabel (otomatis, snapshot, tidak berubah)
            $table->decimal('entry', 15, 2)->nullable()->after('target_price');
            // Entry Lot = input manual user, dasar perhitungan averaging (Lot Avg 1/2/3 = 2x/4x/8x ini)
            $table->integer('entry_lot')->nullable()->after('entry');
        });
    }

    public function down(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            $table->dropColumn(['entry', 'entry_lot']);
        });
    }
};
