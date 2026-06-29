<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Versi ini memperbaiki 2 sumber lag/timeout dari versi sebelumnya:
 *
 * 1. CACHE — harga live di-cache 30 detik per kode saham. Kalau halaman
 *    di-refresh berkali-kali dalam 30 detik, TIDAK ada request baru ke
 *    Yahoo sama sekali, langsung ambil dari cache (instan).
 *
 * 2. PARALLEL FETCH (curl_multi) — kalau ada 20 kode saham unik yang
 *    belum ke-cache, dulu di-fetch SATU PER SATU (20 x 1 detik = 20 detik,
 *    gampang nembus 60 detik kalau jaringan lambat). Sekarang semua
 *    di-fetch BERSAMAAN, jadi totalnya cuma seputar ~2-3 detik saja
 *    (durasi request paling lambat), bukan akumulasi semua request.
 */
class YahooFinanceService
{
    /** Berapa lama harga live disimpan di cache (detik) */
    protected int $cacheTtl = 30;

    /** Batas koneksi & total waktu per request (detik) — lebih ketat & lebih reliable daripada file_get_contents */
    protected int $connectTimeout = 2;
    protected int $totalTimeout = 3;

    /**
     * Ambil harga live untuk SATU kode saham (tetap disediakan untuk kompatibilitas,
     * tapi sebisa mungkin pakai getLivePrices() di bawah untuk banyak kode sekaligus).
     */
    public function getLivePrice(string $stockCode, int $timeout = null): ?float
    {
        $prices = $this->getLivePrices([$stockCode], $timeout);
        return $prices[strtoupper($stockCode)] ?? null;
    }

    /**
     * Ambil harga live untuk BANYAK kode saham sekaligus.
     * - Yang sudah ada di cache (< 30 detik terakhir) langsung dipakai, TANPA request baru.
     * - Yang belum ada di cache, di-fetch PARALEL pakai curl_multi (bukan satu-satu).
     *
     * @param array $stockCodes
     * @return array  ['BBCA' => 9500.0, 'ACES' => null, ...]
     */
    public function getLivePrices(array $stockCodes, int $timeout = null): array
    {
        $stockCodes = array_values(array_unique(array_map('strtoupper', $stockCodes)));
        $result = [];
        $toFetch = [];

        // 1. Cek cache dulu — yang sudah ada, skip total request ke Yahoo
        foreach ($stockCodes as $code) {
            $cacheKey = "yahoo_price_{$code}";
            if (Cache::has($cacheKey)) {
                $result[$code] = Cache::get($cacheKey);
            } else {
                $toFetch[] = $code;
            }
        }

        if (empty($toFetch)) {
            return $result;
        }

        // 2. Fetch sisanya secara PARALEL pakai curl_multi
        $fetched = $this->fetchParallel($toFetch);

        foreach ($fetched as $code => $price) {
            $result[$code] = $price;
            // Simpan ke cache walau null, supaya kode yang gagal/timeout
            // juga tidak langsung di-retry berulang dalam 30 detik berikutnya.
            Cache::put("yahoo_price_{$code}", $price, $this->cacheTtl);
        }

        return $result;
    }

    /**
     * Fetch banyak kode saham secara bersamaan menggunakan curl_multi.
     * Total waktu eksekusi ≈ waktu request paling lambat, BUKAN penjumlahan semua request.
     */
    protected function fetchParallel(array $stockCodes): array
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];

        foreach ($stockCodes as $code) {
            $symbol = $code . '.JK';
            $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . $symbol;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT        => $this->totalTimeout,
                CURLOPT_HTTPHEADER     => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$code] = $ch;
        }

        // Jalankan semua request secara bersamaan
        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle, 0.5);
        } while ($running > 0);

        // Ambil hasil masing-masing
        foreach ($curlHandles as $code => $ch) {
            $response = curl_multi_getcontent($ch);
            $price = null;

            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['chart']['result'][0]['meta']['regularMarketPrice'])) {
                    $price = (float) $data['chart']['result'][0]['meta']['regularMarketPrice'];
                }
            }

            if (curl_errno($ch)) {
                Log::warning("Yahoo fetch error for {$code}: " . curl_error($ch));
            }

            $results[$code] = $price;
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}
