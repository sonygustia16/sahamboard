<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            // Tanggal data saham diambil (snapshot dari tabel ringkasan_saham saat di-star),
            // supaya user tau data watchlist ini narik dari tanggal berapa.
            $table->date('date')->nullable()->after('stock_code');
        });
    }

    public function down(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
