<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrokerSummaryService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        // Dibaca dari config/services.php -> env BROKER_API_BASE / BROKER_API_KEY.
        // Kalau nanti provider ganti lagi (URL/key), cukup update .env,
        // TIDAK perlu sentuh kode ini sama sekali.
        $this->baseUrl = config('services.broker_summary.base_url', 'https://stock.arjum.com/api/broker-summary');
        $this->apiKey  = config('services.broker_summary.api_key');
    }

    /**
     * Header otentikasi sesuai dokumentasi resmi provider (stock.arjum.com):
     * pakai header custom "X-API-Key", BUKAN "Authorization: Bearer".
     */
    protected function authHeaders(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Accept'    => 'application/json',
        ];
    }

    /**
     * Ambil ringkasan broker (buy/sell/netflow) untuk 1 kode saham.
     *
     * @param string $stockCode
     * @param array $options ['start_date','end_date','net','broker_limit','level_limit','all_data']
     * @return array|null
     */
    public function getBrokerSummary(string $stockCode, array $options = []): ?array
    {
        $stockCode = strtoupper($stockCode);

        $params = array_filter([
            'start_date'   => $options['start_date']   ?? null,
            'end_date'     => $options['end_date']      ?? null,
            'net'          => $options['net']           ?? null,
            'broker_limit' => $options['broker_limit']  ?? null,
            'level_limit'  => $options['level_limit']   ?? null,
            'all_data'     => $options['all_data']      ?? null,
        ], fn ($v) => $v !== null);

        if (empty($this->apiKey)) {
            Log::warning('BrokerSummaryService: BROKER_API_KEY belum di-set di .env');
            return null;
        }

        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/{$stockCode}", $params);

            if ($response->failed()) {
                Log::warning("BrokerSummaryService::getBrokerSummary gagal fetch {$stockCode}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("BrokerSummaryService::getBrokerSummary exception {$stockCode}: " . $e->getMessage());
            return null;
        }
    }
/**
     * Ambil broker summary untuk BANYAK stock_code sekaligus secara PARALEL
     * (pakai Http::pool, bukan loop satu-satu), supaya jauh lebih cepat dibanding
     * memanggil getBrokerSummary() berulang di dalam foreach.
     *
     * Dipakai oleh TopMoversController yang perlu scan puluhan saham sekaligus
     * (beda dari Broker Summary/Insider Transaction yang cuma butuh 1 saham per request).
     *
     * @param string[] $stockCodes
     * @param array $options ['start_date','end_date','net','broker_limit','level_limit','all_data']
     * @return array<string, array|null> keyed by stock_code, value = hasil json() atau null kalau gagal
     */
    public function getBrokerSummaryBatch(array $stockCodes, array $options = []): array
    {
        if (empty($this->apiKey) || empty($stockCodes)) {
            return [];
        }

        $params = array_filter([
            'start_date'   => $options['start_date']   ?? null,
            'end_date'     => $options['end_date']      ?? null,
            'net'          => $options['net']           ?? null,
            'broker_limit' => $options['broker_limit']  ?? null,
            'level_limit'  => $options['level_limit']   ?? null,
            'all_data'     => $options['all_data']      ?? null,
        ], fn ($v) => $v !== null);

        try {
            $responses = Http::pool(fn ($pool) => collect($stockCodes)->map(
                fn ($code) => $pool->as($code)
                    ->withHeaders($this->authHeaders())
                    ->timeout(15)
                    ->get("{$this->baseUrl}/{$code}", $params)
            )->all());
        } catch (\Throwable $e) {
            Log::error('BrokerSummaryService::getBrokerSummaryBatch exception: ' . $e->getMessage());
            return [];
        }

        $results = [];
        foreach ($stockCodes as $code) {
            $response = $responses[$code] ?? null;

            if (!$response || $response->failed()) {
                if ($response) {
                    Log::warning("BrokerSummaryService::getBrokerSummaryBatch gagal fetch {$code}", [
                        'status' => $response->status(),
                    ]);
                }
                $results[$code] = null;
                continue;
            }

            $results[$code] = $response->json();
        }

        return $results;
    }
    /**
     * Broker Flow Overlay (dipakai untuk overlay garis broker di chart Value NR).
     *
     * SEMUA angka di sini (garis chart, NET/Akum-distri, B.Avg, S.Avg) mengacu
     * ke SATU periode yang sama: seluruh rentang $dates yang diminta (sesuai
     * timeframe yang dipilih user — 7H/1M/3M/6M/1Y), BUKAN snapshot 1 hari
     * terakhir. Ini supaya konsisten dengan cara kerja tools sejenis (Stockbit dst):
     *
     *  1) TOTAL PERIODE: satu kali panggilan broker-summary dengan start_date/end_date
     *     MENCAKUP SELURUH rentang tanggal (all_data=true). Dari sini didapat:
     *     - nval/nvol per broker = TOTAL net akumulasi/distribusi selama periode
     *       (dipakai sebagai angka "Akum/distri" / NET, dan sebagai titik akhir
     *       garis kumulatif di chart)
     *     - bval/bvol, sval/svol per broker = dipakai hitung B.Avg & S.Avg
     *       SEBAGAI RATA-RATA TERTIMBANG SELURUH PERIODE (bukan snapshot harian)
     *
     *  2) BENTUK GARIS (chart shape): dihitung dari kontribusi harian (fetch per
     *     tanggal, di-downsample untuk rentang panjang spt 1Y) yang diakumulasi
     *     berjalan (running sum), lalu di-skalakan supaya titik AKHIR garis
     *     PERSIS SAMA dengan total periode dari langkah (1) — jadi bentuk garis
     *     tetap representatif, tapi angka akhirnya selalu akurat & konsisten.
     *
     *  3) URUTAN broker: mengikuti urutan asli dari API (nval/nvol DESC
     *     keseluruhan), bukan dikelompokkan blok "semua buyer dulu baru seller".
     *
     * @param string $stockCode
     * @param array $dates array tanggal YYYY-MM-DD (urut sesuai sumbu-X chart)
     * @param string $mode 'value' (pakai net value / nval) | 'volume' (pakai net volume / nvol)
     * @param int $topN jumlah broker teratas yang ditampilkan sebagai overlay
     * @return array|null ['brokers' => [['broker_code','broker_name','data','buy_avg','sell_avg'], ...]]
     */
    public function getBrokerFlow(string $stockCode, array $dates, string $mode = 'value', int $topN = 6): ?array
    {
        $stockCode = strtoupper($stockCode);

        if (empty($this->apiKey)) {
            Log::warning('BrokerSummaryService: BROKER_API_KEY belum di-set di .env');
            return null;
        }

        if (empty($dates)) {
            return ['brokers' => []];
        }

        sort($dates); // pastikan urut ascending sebelum ambil first/last
        $firstDate = $dates[0];
        $lastDate  = end($dates);

        // ── TAHAP 1: Data TOTAL sepanjang periode penuh (satu kali panggilan) ──
        $aggregate = $this->getBrokerSummary($stockCode, [
            'start_date' => $firstDate,
            'end_date'   => $lastDate,
            'all_data'   => true,
        ]);

        if ($aggregate === null || empty($aggregate['brokers'])) {
            Log::warning("BrokerSummaryService::getBrokerFlow tidak ada data agregat untuk {$stockCode} ({$firstDate} s/d {$lastDate})");
            return null;
        }

        $sortKey = $mode === 'volume' ? 'nvol' : 'nval';
        $allBrokers = $aggregate['brokers']; // sudah terurut nval DESC sesuai kontrak API

        // Pisahkan net BUYER (akumulasi, positif) & net SELLER (distribusi, negatif)
        // untuk memastikan overlay menampilkan CAMPURAN keduanya (bukan cuma
        // didominasi satu sisi), tapi urutan TAMPIL tetap ikut urutan asli API.
        $buyers  = array_filter($allBrokers, fn ($b) => ($b[$sortKey] ?? 0) > 0);
        $sellers = array_filter($allBrokers, fn ($b) => ($b[$sortKey] ?? 0) < 0);

        $buyersSorted = $buyers;
        usort($buyersSorted, fn ($a, $b) => ($b[$sortKey] ?? 0) <=> ($a[$sortKey] ?? 0));
        $sellersSorted = $sellers;
        usort($sellersSorted, fn ($a, $b) => abs($b[$sortKey] ?? 0) <=> abs($a[$sortKey] ?? 0));

        $halfN = (int) ceil($topN / 2);
        $topBuyers  = array_slice($buyersSorted, 0, $halfN);
        $topSellers = array_slice($sellersSorted, 0, $topN - count($topBuyers));

        $remaining = $topN - count($topBuyers) - count($topSellers);
        if ($remaining > 0 && count($topBuyers) < count($buyersSorted)) {
            $topBuyers = array_slice($buyersSorted, 0, count($topBuyers) + $remaining);
        } elseif ($remaining > 0 && count($topSellers) < count($sellersSorted)) {
            $topSellers = array_slice($sellersSorted, 0, count($topSellers) + $remaining);
        }

        $topCodesSet = [];
        foreach (array_merge($topBuyers, $topSellers) as $b) {
            if (!empty($b['broker_code'])) {
                $topCodesSet[$b['broker_code']] = true;
            }
        }

        if (empty($topCodesSet)) {
            return ['brokers' => []];
        }

        // Susun ulang sesuai urutan ASLI dari API (bukan blok buyer-dulu-seller),
        // biar tampilannya natural seperti contoh (mis. ZP, BB, YU, XL, DX, AK).
        $orderedTopBrokers = array_values(array_filter(
            $allBrokers,
            fn ($b) => isset($topCodesSet[$b['broker_code'] ?? null])
        ));

        $topCodes = [];
        $brokerMeta = [];
        $totalNetByCode = [];   // total akumulasi/distribusi periode penuh
        $periodBuyAvg   = [];   // rata-rata beli tertimbang sepanjang periode
        $periodSellAvg  = [];   // rata-rata jual tertimbang sepanjang periode

        foreach ($orderedTopBrokers as $b) {
            $code = $b['broker_code'];
            $topCodes[] = $code;
            $brokerMeta[$code] = $b['broker_name'] ?? $code;
            $totalNetByCode[$code] = $b[$sortKey] ?? 0;

            $bvol = $b['bvol'] ?? 0;
            $svol = $b['svol'] ?? 0;
            $periodBuyAvg[$code]  = $bvol > 0 ? round(($b['bval'] ?? 0) / $bvol, 2) : null;
            $periodSellAvg[$code] = $svol > 0 ? round(($b['sval'] ?? 0) / $svol, 2) : null;
        }

        // ── TAHAP 2: Bentuk garis harian (di-downsample untuk rentang panjang) ──
        $sampledDates = $this->downsampleDates($dates, 90);

        try {
            $responses = Http::pool(fn ($pool) => collect($sampledDates)->map(
                fn ($date) => $pool->as($date)
                    ->withHeaders($this->authHeaders())
                    ->timeout(15)
                    ->get("{$this->baseUrl}/{$stockCode}", [
                        'start_date'   => $date,
                        'end_date'     => $date,
                        'broker_limit' => 50,
                    ])
            )->all());
        } catch (\Throwable $e) {
            Log::error("BrokerSummaryService::getBrokerFlow exception {$stockCode}: " . $e->getMessage());
            return null;
        }

        $dailyContribution = []; // broker_code => [date => nilai harian]
        $anySuccess = false;

        foreach ($sampledDates as $date) {
            $response = $responses[$date] ?? null;

            if (!$response || $response->failed()) {
                if ($response) {
                    Log::warning("BrokerSummaryService::getBrokerFlow gagal fetch tanggal {$date} untuk {$stockCode}", [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                }
                continue;
            }

            $anySuccess = true;
            $brokers = $response->json('brokers') ?? [];

            foreach ($brokers as $b) {
                $code = $b['broker_code'] ?? null;
                if (!$code || !isset($topCodesSet[$code])) continue;

                $dailyContribution[$code][$date] = $mode === 'volume' ? ($b['nvol'] ?? 0) : ($b['nval'] ?? 0);
            }
        }

        if (!$anySuccess) {
            return null;
        }

        $result = [];
        foreach ($topCodes as $code) {
            // Kumulatif berjalan sepanjang $dates penuh (carry-forward, tanpa null),
            // supaya bentuk garis mirip Stockbit: naik/turun mengikuti aktivitas,
            // rata kalau tidak ada transaksi di hari itu.
            $cumulative = [];
            $running = 0.0;
            foreach ($dates as $date) {
                $running += $dailyContribution[$code][$date] ?? 0;
                $cumulative[] = $running;
            }

            // Skalakan supaya titik AKHIR garis PERSIS SAMA dengan total resmi
            // dari broker-summary periode penuh (Tahap 1) — jadi angka legend,
            // tabel, dan ujung garis chart selalu konsisten satu sama lain.
            $totalNet = $totalNetByCode[$code] ?? 0;
            $rawFinal = end($cumulative) ?: 0;

            if ($rawFinal != 0) {
                $scale = $totalNet / $rawFinal;
                $cumulative = array_map(fn ($v) => round($v * $scale, 2), $cumulative);
            } elseif ($totalNet != 0) {
                // Tidak ada kontribusi harian tersampel sama sekali, tapi totalnya
                // bukan nol (mis. semua terjadi di tanggal yang tidak ke-sample) —
                // taruh langsung di titik terakhir supaya angka tetap akurat.
                $cumulative[count($cumulative) - 1] = $totalNet;
            }

            // buy_avg/sell_avg konstan (rata-rata tertimbang periode penuh) di
            // setiap titik — bukan snapshot per hari — sesuai semantik "Akum/distri".
            $buyAvgArr  = array_fill(0, count($dates), $periodBuyAvg[$code] ?? null);
            $sellAvgArr = array_fill(0, count($dates), $periodSellAvg[$code] ?? null);

            $result[] = [
                'broker_code' => $code,
                'broker_name' => $brokerMeta[$code] ?? $code,
                'data'        => $cumulative,
                'buy_avg'     => $buyAvgArr,
                'sell_avg'    => $sellAvgArr,
            ];
        }

        return ['brokers' => $result];
    }

    /**
     * Ambil sampel tanggal menyebar merata dari awal sampai akhir array,
     * maksimal $maxPoints titik. Kalau jumlah tanggal <= $maxPoints,
     * kembalikan apa adanya (tidak perlu di-downsample).
     */
    private function downsampleDates(array $dates, int $maxPoints): array
    {
        $count = count($dates);

        if ($count <= $maxPoints) {
            return $dates;
        }

        $step = $count / $maxPoints;
        $sampled = [];

        for ($i = 0; $i < $maxPoints; $i++) {
            $idx = min((int) round($i * $step), $count - 1);
            $sampled[$dates[$idx]] = $dates[$idx]; // pakai key untuk dedupe otomatis
        }

        // Pastikan tanggal paling akhir selalu ikut, biar overlay "nyampe" ke hari ini
        $sampled[$dates[$count - 1]] = $dates[$count - 1];

        return array_values($sampled);
    }
}
