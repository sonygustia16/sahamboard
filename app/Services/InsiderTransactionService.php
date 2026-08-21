<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Konsumsi endpoint Transaksi Insider dari provider stock.arjum.com.
 * Polanya sengaja disamakan persis dengan BrokerSummaryService supaya
 * konsisten & gampang dirawat (satu provider, header auth sama).
 */
class InsiderTransactionService
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        // Dibaca dari config/services.php -> env INSIDER_API_BASE / BROKER_API_KEY
        // (API key SAMA dengan Broker Summary, cuma base URL beda).
        $this->baseUrl = config('services.insider.base_url', 'https://stock.arjum.com/api/insider');
        $this->apiKey  = config('services.insider.api_key');
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
     * Ambil daftar transaksi insider untuk 1 kode saham.
     *
     * @param string $stockCode
     * @param array $options ['page','limit','action_type']  action_type: buy|sell|cross
     * @return array|null  ['stock_code','count','total','page','page_size','total_pages','items' => [...]]
     */
    public function getInsiderTransactions(string $stockCode, array $options = []): ?array
    {
        $stockCode = strtoupper($stockCode);

        $params = array_filter([
            'page'        => $options['page']        ?? null,
            'limit'       => $options['limit']        ?? null,
            'action_type' => $options['action_type']  ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if (empty($this->apiKey)) {
            Log::warning('InsiderTransactionService: BROKER_API_KEY belum di-set di .env');
            return null;
        }

        try {
            $response = Http::withHeaders($this->authHeaders())
                ->timeout(15)
                ->get("{$this->baseUrl}/{$stockCode}", $params);

            if ($response->failed()) {
                Log::warning("InsiderTransactionService::getInsiderTransactions gagal fetch {$stockCode}", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::error("InsiderTransactionService::getInsiderTransactions exception {$stockCode}: " . $e->getMessage());
            return null;
        }
    }
}
