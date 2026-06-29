<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migration ini HANYA untuk dokumentasi/setup ulang jika diperlukan.
     * Jika tabel ringkasan_saham SUDAH ADA di database Anda, JANGAN jalankan
     * migration ini — cukup pastikan nama kolom di Model RingkasanSaham
     * sama dengan kolom yang sudah ada.
     */
    public function up(): void
    {
        if (!Schema::hasTable('ringkasan_saham')) {
            Schema::create('ringkasan_saham', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('stock_code', 10);
                $table->decimal('previous', 15, 2)->default(0);
                $table->unsignedBigInteger('frequency')->default(0);
                $table->decimal('value', 20, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ringkasan_saham');
    }
};
