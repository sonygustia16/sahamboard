<?php

namespace App\Http\Controllers;

use App\Services\InsiderTransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoint internal yang dipanggil dari JS (fetch) di screening.blade.php:
 * - GET /insider-transaction/{stockCode}  -> panel tabel Transaksi Insider
 *
 * Cuma jembatan tipis ke InsiderTransactionService + caching singkat, supaya:
 * - API key TIDAK pernah terekspos ke browser (aman, key cuma hidup di server).
 * - User yang gonta-ganti tab/filter/halaman tidak membombardir API eksternal
 *   dengan request identik dalam waktu singkat.
 */
class InsiderTransactionController extends Controller
{
    protected InsiderTransactionService $insiderTransaction;

    public function __construct(InsiderTransactionService $insiderTransaction)
    {
        $this->insiderTransaction = $insiderTransaction;
    }

    /**
     * GET /insider-transaction/{stockCode}
     * Query: page, limit, action_type (buy|sell|cross)
     */
    public function show(Request $request, string $stockCode)
    {
        $stockCode = strtoupper($stockCode);

        $options = array_filter([
            'page'        => $request->query('page'),
            'limit'       => $request->query('limit'),
            'action_type' => $request->query('action_type'),
        ], fn ($v) => $v !== null && $v !== '');

        $cacheKey = 'insider_transaction_' . $stockCode . '_' . md5(json_encode($options));

        $data = Cache::remember($cacheKey, 30, function () use ($stockCode, $options) {
            return $this->insiderTransaction->getInsiderTransactions($stockCode, $options);
        });

        if ($data === null) {
            Cache::forget($cacheKey);

            return response()->json([
                'error' => 'Gagal mengambil data transaksi insider. Coba lagi sebentar lagi.',
            ], 502);
        }

        return response()->json($data);
    }
}
