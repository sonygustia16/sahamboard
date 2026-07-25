<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migration idempotent — cek dulu tiap kolom sebelum nambah.
 *
 * PERBAIKAN v4 (fix akar masalah SQLSTATE[25P02] di Neon Postgres):
 * - v3 sudah benar soal $withinTransaction = false, TAPI ternyata root cause
 *   sebenarnya bukan di situ. Query yang gagal di log Render adalah query
 *   introspeksi kolom bawaan DOCTRINE DBAL (dipakai otomatis di belakang
 *   layar oleh Schema::hasColumn() / Schema::hasTable()). Query Doctrine ini
 *   (join pg_attribute + pg_class + pg_type + pg_namespace) tidak selalu
 *   kompatibel dengan Neon POOLER (PgBouncer transaction-pooling mode).
 * - v4: BUANG SAMA SEKALI pemakaian Schema::hasColumn()/hasTable(). Ganti
 *   dengan query manual ke information_schema (raw SQL sederhana), persis
 *   pola yang sudah dipakai di indexExists() di bawah untuk cek index.
 *   Ini query native Postgres biasa, tidak lewat Doctrine sama sekali.
 */
return new class extends Migration
{
    /**
     * Tetap matikan transaction wrapping bawaan Laravel untuk migration ini.
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
        if (!$this->tableExists('ringkasan_saham')) {
            Log::warning('[migration ensure_screening_columns] tabel ringkasan_saham tidak ditemukan, migration di-skip.');
            return;
        }

        foreach ($this->columns as $name => [$type, $args]) {
            try {
                if ($this->columnExists('ringkasan_saham', $name)) {
                    continue; // sudah ada, skip
                }

                Schema::table('ringkasan_saham', function (Blueprint $table) use ($name, $type, $args) {
                    $table->{$type}($name, ...$args)->default(0);
                });

                Log::info("[migration ensure_screening_columns] kolom '{$name}' berhasil ditambahkan.");
            } catch (\Throwable $e) {
                // Kalau 1 kolom gagal, jangan hentikan migration — log biar bisa dicek,
                // lanjut ke kolom berikutnya. Karena tiap operasi di-autocommit
                // (withinTransaction=false), kegagalan di sini tidak mencemari
                // statement berikutnya.
                Log::error("[migration ensure_screening_columns] GAGAL nambah kolom '{$name}': " . $e->getMessage());
            }
        }

        // Index tambahan
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

    /**
     * Ganti Schema::hasTable() — raw query ke information_schema,
     * tidak lewat Doctrine DBAL sama sekali.
     */
    private function tableExists(string $table): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            );
            return count($rows) > 0;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1",
                [$table]
            );
            return count($rows) > 0;
        }

        $rows = DB::select(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table]
        );

        return count($rows) > 0;
    }

    /**
     * Ganti Schema::hasColumn() — raw query ke information_schema,
     * tidak lewat Doctrine DBAL sama sekali. Ini yang jadi biang error
     * SQLSTATE[25P02] sebelumnya.
     */
    private function columnExists(string $table, string $column): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA table_info('{$table}')");
            foreach ($rows as $row) {
                if ($row->name === $column) {
                    return true;
                }
            }
            return false;
        }

        if ($driver === 'pgsql') {
            $rows = DB::select(
                "SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1",
                [$table, $column]
            );
            return count($rows) > 0;
        }

        $rows = DB::select(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column]
        );

        return count($rows) > 0;
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
