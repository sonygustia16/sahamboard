<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrokerSummaryService
{
    protected string $baseUrl;
    protected string $flowBaseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        // Semua dibaca dari config/services.php -> env BROKER_API_BASE / BROKER_FLOW_API_BASE / BROKER_API_KEY.
        // Kalau nanti provider ganti lagi (URL/key), cukup update .env,
        // TIDAK perlu sentuh kode ini sama sekali.
        $this->baseUrl     = config('services.broker_summary.base_url', 'https://stock.arjum.com/api/broker-summary');
        $this->flowBaseUrl = config('services.broker_summary.flow_base_url', 'https://stock.arjum.com/api/broker-flow');
        $this->apiKey      = config('services.broker_summary.api_key');
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
     * Ambil data broker flow (dipakai untuk overlay garis broker di chart Value NR).
     *
     * @param string $stockCode
     * @param array $dates array tanggal YYYY-MM-DD yang mau diambil datanya
     * @param string $mode 'value' | 'volume'
     * @return array|null
     */
    public function getBrokerFlow(string $stockCode, array $dates, string $mode = 'value'): ?array
    {
        $stockCode = strtoupper($stockCode);

        if (empty($this->apiKey)) {
            Log::warning('BrokerSummaryService: BROKER_API_KEY belum di-set di .env');
            return null;
        }

        if (empty($dates)) {
            return null;
        }

        $params = [
            'dates' => implode(',', $dates),
            'mode'  => in_array($mode, ['value', 'volume'], true) ? $mode : 'value',
        ];

        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->get("{$this->flowBaseUrl}/{$stockCode}", $params);

            if ($response->failed()) {
                Log::warning("BrokerSummaryService::getBrokerFlow gagal fetch {$stockCode}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("BrokerSummaryService::getBrokerFlow exception {$stockCode}: " . $e->getMessage());
            return null;
        }
    }
}
