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
     * PENTING: Provider (stock.arjum.com) TIDAK menyediakan endpoint khusus "broker-flow" —
     * hanya 8 endpoint resmi yang salah satunya "broker-summary". Jadi data flow di sini
     * DIHITUNG SENDIRI dengan memanggil broker-summary untuk SETIAP tanggal yang diminta
     * (start_date = end_date = tanggal tsb), lalu digabung jadi deret waktu per broker.
     *
     * Supaya tidak lambat (N tanggal = N request berurutan), semua request dijalankan
     * PARALEL lewat Http::pool().
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

        // Batasi maksimal 60 tanggal terakhir, biar tidak generate ratusan request
        // paralel sekaligus kalau user pilih rentang timeframe yang sangat panjang (mis. 1Y).
        $dates = array_slice($dates, -60);

        try {
            $responses = Http::pool(fn ($pool) => collect($dates)->map(
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

        $brokerMeta      = []; // broker_code => broker_name
        $seriesByBroker  = []; // broker_code => [date => net value/volume]
        $buyAvgByBroker  = []; // broker_code => [date => buy avg price]
        $sellAvgByBroker = []; // broker_code => [date => sell avg price]
        $anySuccess      = false;

        foreach ($dates as $date) {
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
            $json = $response->json();
            $brokers = $json['brokers'] ?? [];

            foreach ($brokers as $b) {
                $code = $b['broker_code'] ?? null;
                if (!$code) continue;

                $brokerMeta[$code] = $b['broker_name'] ?? $code;

                $value = $mode === 'volume' ? ($b['nvol'] ?? 0) : ($b['nval'] ?? 0);
                $seriesByBroker[$code][$date] = $value;

                $bvol = $b['bvol'] ?? 0;
                $svol = $b['svol'] ?? 0;
                $buyAvgByBroker[$code][$date]  = $bvol > 0 ? round(($b['bval'] ?? 0) / $bvol, 2) : null;
                $sellAvgByBroker[$code][$date] = $svol > 0 ? round(($b['sval'] ?? 0) / $svol, 2) : null;
            }
        }

        // Kalau SEMUA tanggal gagal fetch, anggap gagal total (biar controller balas 502,
        // bukan overlay kosong yang terlihat seperti "berhasil tapi tidak ada broker").
        if (!$anySuccess) {
            return null;
        }

        // Tentukan top-N broker paling aktif sepanjang periode (berdasar total |net value|)
        $totals = [];
        foreach ($seriesByBroker as $code => $byDate) {
            $totals[$code] = array_sum(array_map('abs', $byDate));
        }
        arsort($totals);
        $topCodes = array_slice(array_keys($totals), 0, $topN);

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
}
