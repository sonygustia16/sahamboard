<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel ringkasan_saham dipakai untuk filter by stock_code, date range,
 * dan orderBy(date, value) di StockFilterController & StockAnalysisController.
 * Tanpa index, semua query ini full table scan — makin lambat seiring
 * data bertambah (data ditarik otomatis tiap hari oleh worker Python).
 *
 * Index dibuat idempotent (cek dulu sebelum nambah) supaya aman dijalankan
 * di database yang tabelnya sudah ada lebih dulu (lihat catatan di migration
 * create_ringkasan_saham_table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ringkasan_saham')) {
            return;
        }

        Schema::table('ringkasan_saham', function (Blueprint $table) {
            if (!$this->indexExists('ringkasan_saham', 'ringkasan_saham_stock_code_index')) {
                $table->index('stock_code');
            }
            if (!$this->indexExists('ringkasan_saham', 'ringkasan_saham_date_index')) {
                $table->index('date');
            }
            if (!$this->indexExists('ringkasan_saham', 'ringkasan_saham_stock_code_date_index')) {
                $table->index(['stock_code', 'date']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ringkasan_saham')) {
            return;
        }

        Schema::table('ringkasan_saham', function (Blueprint $table) {
            $table->dropIndex('ringkasan_saham_stock_code_index');
            $table->dropIndex('ringkasan_saham_date_index');
            $table->dropIndex('ringkasan_saham_stock_code_date_index');
        });
    }

    /**
     * Cek keberadaan index tanpa bergantung ke doctrine/dbal (query information_schema
     * langsung, kompatibel dengan MySQL/MariaDB — driver yang umum dipakai Railway).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if ($row->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName]
        );

        return count($rows) > 0;
    }
};
