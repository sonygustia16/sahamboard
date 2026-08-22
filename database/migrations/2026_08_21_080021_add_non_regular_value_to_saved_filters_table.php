<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: dicek per-kolom dulu sebelum ditambahkan. Ini penting khusus
        // untuk Postgres (Neon) — kalau salah satu ALTER TABLE gagal (misal kolom
        // sudah ada dari percobaan migrate sebelumnya), seluruh transaksi migration
        // ini langsung "aborted" dan statement berikutnya ikut gagal semua.
        // Dengan pengecekan ini, migration aman dijalankan ulang kapan pun.
        Schema::table('saved_filters', function (Blueprint $table) {
            if (!Schema::hasColumn('saved_filters', 'op_non_regular_value')) {
                $table->string('op_non_regular_value', 2)->default('=')->after('value');
            }
        });

        Schema::table('saved_filters', function (Blueprint $table) {
            if (!Schema::hasColumn('saved_filters', 'non_regular_value')) {
                $table->unsignedBigInteger('non_regular_value')->nullable()->after('op_non_regular_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('saved_filters', function (Blueprint $table) {
            if (Schema::hasColumn('saved_filters', 'op_non_regular_value') || Schema::hasColumn('saved_filters', 'non_regular_value')) {
                $columns = array_filter(
                    ['op_non_regular_value', 'non_regular_value'],
                    fn ($col) => Schema::hasColumn('saved_filters', $col)
                );
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            }
        });
    }
};
