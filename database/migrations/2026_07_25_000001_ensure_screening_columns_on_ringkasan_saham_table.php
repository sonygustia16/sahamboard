<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration idempotent — cek dulu tiap kolom sebelum nambah, aman dijalankan
 * di database yang sudah punya sebagian/semua kolom ini.
 *
 * PERBAIKAN dari versi sebelumnya: SEMUA ->after() dihapus.
 * PostgreSQL (Neon) TIDAK mendukung positional column (ADD COLUMN ... AFTER x)
 * seperti MySQL/MariaDB — kolom baru otomatis ditaruh di akhir tabel. Memakai
 * ->after() di driver pgsql menyebabkan SQL syntax error yang bikin seluruh
 * migration gagal (transaction aborted).
 *
 * Kolom-kolom ini dipakai oleh screening.blade.php opsi baru:
 * - non_regular_value        -> basis "akumulasi_nrv" (Backtest 2)
 * - foreign_buy/foreign_sell -> basis konfirmasi "akumulasi_ketat" (Backtest 3c)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ringkasan_saham')) {
            return;
        }

        Schema::table('ringkasan_saham', function (Blueprint $table) {
            if (!Schema::hasColumn('ringkasan_saham', 'close')) {
                $table->decimal('close', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'volume')) {
                $table->unsignedBigInteger('volume')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'non_regular_value')) {
                $table->decimal('non_regular_value', 20, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'non_regular_volume')) {
                $table->unsignedBigInteger('non_regular_volume')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'non_regular_frequency')) {
                $table->unsignedBigInteger('non_regular_frequency')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'foreign_buy')) {
                $table->unsignedBigInteger('foreign_buy')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'foreign_sell')) {
                $table->unsignedBigInteger('foreign_sell')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'open_price')) {
                $table->decimal('open_price', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'high')) {
                $table->decimal('high', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'low')) {
                $table->decimal('low', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'bid')) {
                $table->decimal('bid', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'bid_volume')) {
                $table->unsignedBigInteger('bid_volume')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'offer')) {
                $table->decimal('offer', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'offer_volume')) {
                $table->unsignedBigInteger('offer_volume')->default(0);
            }
            if (!Schema::hasColumn('ringkasan_saham', 'listed_shares')) {
                $table->unsignedBigInteger('listed_shares')->default(0);
            }
        });

        // Index tambahan buat query screening (join self-table butuh lookup cepat by stock_code+close)
        if (!$this->indexExists('ringkasan_saham', 'ringkasan_saham_stock_code_close_index')) {
            Schema::table('ringkasan_saham', function (Blueprint $table) {
                $table->index(['stock_code', 'close']);
            });
        }
    }

    public function down(): void
    {
        // Sengaja tidak drop kolom di down() — kolom ini dipakai fitur lain (chart, screening lama)
        // dan berisiko data loss kalau di-rollback tanpa sengaja. Kalau memang perlu drop,
        // lakukan manual.
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = \Illuminate\Support\Facades\DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if ($row->name === $indexName) {
                    return true;
                }
            }
            return false;
        }

        if ($driver === 'pgsql') {
            $rows = \Illuminate\Support\Facades\DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName]
            );
            return count($rows) > 0;
        }

        $rows = \Illuminate\Support\Facades\DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName]
        );

        return count($rows) > 0;
    }
};
