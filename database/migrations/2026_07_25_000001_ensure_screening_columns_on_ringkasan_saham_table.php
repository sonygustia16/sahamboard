<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migration idempotent — cek dulu tiap kolom sebelum nambah.
 *
 * PERBAIKAN v3:
 * - $withinTransaction = false -> Laravel/Postgres TIDAK membungkus semua
 *   statement migration ini jadi 1 transaksi. Ini penting karena host DB
 *   kamu pakai Neon POOLER (PgBouncer) yang sering bikin transaksi panjang
 *   "aborted" secara misterius dan menutupi error asli. Dengan ini, kalau
 *   ada statement yang gagal, errornya akan tampil ASLI (bukan cascading
 *   "current transaction is aborted").
 * - Tiap kolom ditambah satu-satu di dalam try/catch sendiri + di-log,
 *   supaya SATU kolom gagal tidak menggagalkan seluruh migration, dan kita
 *   bisa lihat persis kolom mana + pesan error apa dari log Render kalau
 *   masih ada yang gagal.
 */
return new class extends Migration
{
    /**
     * Jangan bungkus migration ini dalam 1 transaksi.
     * @var bool
     */
    public $withinTransaction = false;

    private array $columns = [
        'close'                 => ['decimal', [15, 2]],
        'volume'                => ['unsignedBigInteger', []],
        'non_regular_value'     => ['decimal', [20, 2]],
        'non_regular_volume'    => ['unsignedBigInteger', []],
        'non_regular_frequency' => ['unsignedBigInteger', []],
        'foreign_buy'           => ['unsignedBigInteger', []],
        'foreign_sell'          => ['unsignedBigInteger', []],
        'open_price'            => ['decimal', [15, 2]],
        'high'                  => ['decimal', [15, 2]],
        'low'                   => ['decimal', [15, 2]],
        'bid'                   => ['decimal', [15, 2]],
        'bid_volume'            => ['unsignedBigInteger', []],
        'offer'                 => ['decimal', [15, 2]],
        'offer_volume'          => ['unsignedBigInteger', []],
        'listed_shares'         => ['unsignedBigInteger', []],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('ringkasan_saham')) {
            Log::warning('[migration ensure_screening_columns] tabel ringkasan_saham tidak ditemukan, migration di-skip.');
            return;
        }

        foreach ($this->columns as $name => [$type, $args]) {
            try {
                if (Schema::hasColumn('ringkasan_saham', $name)) {
                    continue; // sudah ada, skip
                }

                Schema::table('ringkasan_saham', function (Blueprint $table) use ($name, $type, $args) {
                    $table->{$type}($name, ...$args)->default(0);
                });

                Log::info("[migration ensure_screening_columns] kolom '{$name}' berhasil ditambahkan.");
            } catch (\Throwable $e) {
                // Jangan hentikan migration cuma karena 1 kolom gagal — log biar bisa dicek,
                // lanjut ke kolom berikutnya.
                Log::error("[migration ensure_screening_columns] GAGAL nambah kolom '{$name}': " . $e->getMessage());
            }
        }

        // Index tambahan — juga dibungkus try/catch terpisah, tidak boleh
        // menggagalkan migration keseluruhan kalau gagal.
        try {
            if (!$this->indexExists('ringkasan_saham', 'ringkasan_saham_stock_code_close_index')) {
                Schema::table('ringkasan_saham', function (Blueprint $table) {
                    $table->index(['stock_code', 'close']);
                });
                Log::info('[migration ensure_screening_columns] index stock_code+close berhasil ditambahkan.');
            }
        } catch (\Throwable $e) {
            Log::error('[migration ensure_screening_columns] GAGAL nambah index: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Sengaja tidak drop kolom di down() — kolom ini dipakai fitur lain (chart, screening lama)
        // dan berisiko data loss kalau di-rollback tanpa sengaja.
    }

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

        if ($driver === 'pgsql') {
            $rows = DB::select(
                'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1',
                [$table, $indexName]
            );
            return count($rows) > 0;
        }

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $indexName]
        );

        return count($rows) > 0;
    }
};
