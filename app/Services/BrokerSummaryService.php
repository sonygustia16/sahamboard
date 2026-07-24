<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrokerSummaryService
{
    protected string $baseUrl = 'https://cuan.jumari.app/api/broker-summary';

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

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/{$stockCode}", $params);

            if ($response->failed()) {
                Log::warning("BrokerSummaryService: gagal fetch {$stockCode}", [
                    'status' => $response->status(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("BrokerSummaryService: exception {$stockCode}: " . $e->getMessage());
            return null;
        }
    }
}
