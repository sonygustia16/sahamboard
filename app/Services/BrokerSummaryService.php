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
     * Broker Flow Overlay (dipakai untuk overlay garis broker di chart Value NR).
     *
     * Provider (stock.arjum.com) TIDAK menyediakan endpoint khusus "broker-flow" —
     * hanya 8 endpoint resmi, salah satunya "broker-summary". Jadi flow di sini
     * dibangun dari broker-summary dengan 2 tahap:
     *
     *  1) TOP-N BROKER: satu kali panggilan broker-summary dengan start_date/end_date
     *     MENCAKUP SELURUH rentang tanggal yang diminta (all_data=true). Ini yang
     *     bikin daftar broker beneran DINAMIS mengikuti timeframe yang dipilih user
     *     (1M/3M/6M/1Y) — bukan selalu berdasar beberapa hari terakhir saja.
     *
     *  2) DERET WAKTU: untuk menggambar garis overlay per hari, idealnya perlu data
     *     harian sepanjang periode. Supaya tidak generate ratusan request paralel
     *     untuk rentang panjang (mis. 1Y ~250 hari bursa), tanggal di-DOWNSAMPLE
     *     MERATA (ambil sampel titik menyebar dari awal s/d akhir periode) —
     *     BUKAN dipotong ke beberapa hari terakhir saja. Titik yang tidak ikut
     *     ter-sample diisi null; dataset overlay di frontend sudah pakai
     *     spanGaps:true jadi garis tetap tersambung mulus.
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

        // ── TAHAP 1: Top-N broker paling aktif SEPANJANG PERIODE PENUH ──
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

        $sortedBrokers = $aggregate['brokers'];
        usort($sortedBrokers, fn ($a, $b) => abs($b[$sortKey] ?? 0) <=> abs($a[$sortKey] ?? 0));
        $topBrokers = array_slice($sortedBrokers, 0, $topN);

        $topCodes = [];
        $brokerMeta = [];
        foreach ($topBrokers as $b) {
            if (empty($b['broker_code'])) continue;
            $topCodes[] = $b['broker_code'];
            $brokerMeta[$b['broker_code']] = $b['broker_name'] ?? $b['broker_code'];
        }

        if (empty($topCodes)) {
            return ['brokers' => []];
        }

        // ── TAHAP 2: Deret waktu harian untuk broker-broker top itu, di-downsample
        //    supaya mencakup SELURUH periode tanpa request meledak ──
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

        $seriesByBroker  = []; // broker_code => [date => net value/volume]
        $buyAvgByBroker  = []; // broker_code => [date => buy avg price]
        $sellAvgByBroker = []; // broker_code => [date => sell avg price]
        $anySuccess      = false;

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
                if (!$code || !in_array($code, $topCodes, true)) continue;

                $value = $mode === 'volume' ? ($b['nvol'] ?? 0) : ($b['nval'] ?? 0);
                $seriesByBroker[$code][$date] = $value;

                $bvol = $b['bvol'] ?? 0;
                $svol = $b['svol'] ?? 0;
                $buyAvgByBroker[$code][$date]  = $bvol > 0 ? round(($b['bval'] ?? 0) / $bvol, 2) : null;
                $sellAvgByBroker[$code][$date] = $svol > 0 ? round(($b['sval'] ?? 0) / $svol, 2) : null;
            }
        }

        if (!$anySuccess) {
            return null;
        }

        // Kembalikan data untuk SEMUA tanggal asli ($dates), supaya jumlah titik
        // selaras dengan sumbu-X chart utama. Tanggal yang tidak ikut ter-sample
        // otomatis null (spanGaps:true di frontend bikin garis tetap mulus).
        $result = [];
        foreach ($topCodes as $code) {
            $data = [];
            $buyAvg = [];
            $sellAvg = [];

            foreach ($dates as $date) {
                $data[]    = $seriesByBroker[$code][$date]  ?? null;
                $buyAvg[]  = $buyAvgByBroker[$code][$date]  ?? null;
                $sellAvg[] = $sellAvgByBroker[$code][$date] ?? null;
            }

            $result[] = [
                'broker_code' => $code,
                'broker_name' => $brokerMeta[$code] ?? $code,
                'data'        => $data,
                'buy_avg'     => $buyAvg,
                'sell_avg'    => $sellAvg,
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
