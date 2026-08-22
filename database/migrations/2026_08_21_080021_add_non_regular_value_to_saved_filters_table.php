<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matikan pembungkusan otomatis migration ini dalam 1 DB transaction.
     *
     * Kenapa: koneksi production pakai Neon Postgres lewat connection pooler
     * (PgBouncer, terlihat dari host "...-pooler..."). Kombinasi pooler +
     * transaksi otomatis Laravel untuk migration sering bikin error rancu
     * "current transaction is aborted" padahal statement-nya sendiri valid
     * dan kolomnya belum ada. Menonaktifkan wrapping ini adalah solusi resmi
     * Laravel untuk kasus seperti ini (dan juga wajib untuk DDL semacam
     * CREATE INDEX CONCURRENTLY di Postgres).
     */
    public $withinTransaction = false;

    public function up(): void
    {
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