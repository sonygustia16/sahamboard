<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fix tipe kolom "close" yang ternyata sudah ada duluan di production
 * sebagai BIGINT (bukan DECIMAL), sehingga query:
 *   whereRaw('curr.close < prev.close * ?', [0.99])
 * gagal dengan:
 *   SQLSTATE[22P02]: invalid input syntax for type bigint: "0.99"
 *
 * Migration sebelumnya (ensure_screening_columns) tidak menyentuh kolom ini
 * karena sudah "ada" (hasColumn/columnExists true), padahal tipe datanya salah.
 *
 * Migration ini eksplisit ALTER COLUMN TYPE, aman dijalankan berkali-kali
 * (idempotent secara efek — kalau tipe sudah numeric, ALTER ini no-op secara hasil).
 */
return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Kolom yang perlu dipastikan numeric/decimal, bukan integer.
     * Tambahkan kolom lain di sini kalau kamu curiga ada tipe salah serupa.
     */
    private array $numericColumns = [
        'close'      => '15,2',
        'previous'   => '15,2',
        'open_price' => '15,2',
        'high'       => '15,2',
        'low'        => '15,2',
        'bid'        => '15,2',
        'offer'      => '15,2',
    ];

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return; // fix ini spesifik Postgres
        }

        if (!$this->tableExists('ringkasan_saham')) {
            Log::warning('[migration fix_close_column_type] tabel ringkasan_saham tidak ditemukan, skip.');
            return;
        }

        foreach ($this->numericColumns as $column => $precision) {
            try {
                $currentType = $this->getColumnType('ringkasan_saham', $column);

                if ($currentType === null) {
                    Log::info("[migration fix_close_column_type] kolom '{$column}' tidak ada, skip.");
                    continue;
                }

                if (in_array($currentType, ['numeric', 'decimal'], true)) {
                    Log::info("[migration fix_close_column_type] kolom '{$column}' sudah numeric, skip.");
                    continue;
                }

                DB::statement(
                    "ALTER TABLE ringkasan_saham ALTER COLUMN {$column} TYPE numeric({$precision}) USING {$column}::numeric"
                );

                Log::info("[migration fix_close_column_type] kolom '{$column}' berhasil diubah dari '{$currentType}' ke numeric({$precision}).");
            } catch (\Throwable $e) {
                Log::error("[migration fix_close_column_type] GAGAL ubah tipe kolom '{$column}': " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Sengaja tidak di-rollback ke bigint — itu tipe yang salah dari awal.
    }

    private function tableExists(string $table): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? LIMIT 1",
            [$table]
        );
        return count($rows) > 0;
    }

    private function getColumnType(string $table, string $column): ?string
    {
        $rows = DB::select(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1",
            [$table, $column]
        );

        return $rows[0]->data_type ?? null;
    }
};
