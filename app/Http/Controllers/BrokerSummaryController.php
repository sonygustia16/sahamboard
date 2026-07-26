<?php

namespace App\Http\Controllers;

use App\Services\BrokerSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Endpoint internal yang dipanggil dari JS (fetch) di screening.blade.php:
 * - GET /broker-summary/{stockCode}  -> panel tabel agregat Buy/Sell/Net
 * - GET /broker-flow/{stockCode}     -> overlay garis broker di chart Value NR
 *
 * Keduanya cuma jembatan tipis ke BrokerSummaryService + caching singkat, supaya:
 * - API key TIDAK pernah terekspos ke browser (aman, key cuma hidup di server).
 * - User yang gonta-ganti tab/toggle/timeframe tidak membombardir API eksternal
 *   dengan request identik dalam waktu singkat.
 */
class BrokerSummaryController extends Controller
{
    protected BrokerSummaryService $brokerSummary;

    public function __construct(BrokerSummaryService $brokerSummary)
    {
        $this->brokerSummary = $brokerSummary;
    }

    /**
     * GET /broker-summary/{stockCode}
     * Query: start_date, end_date, net, broker_limit, level_limit, all_data
     */
    public function show(Request $request, string $stockCode)
    {
        $stockCode = strtoupper($stockCode);

        $options = array_filter([
            'start_date'   => $request->query('start_date'),
            'end_date'     => $request->query('end_date'),
            'net'          => $request->query('net'),
            'broker_limit' => $request->query('broker_limit'),
            'level_limit'  => $request->query('level_limit'),
            'all_data'     => $request->query('all_data'),
        ], fn ($v) => $v !== null && $v !== '');

        $cacheKey = 'broker_summary_' . $stockCode . '_' . md5(json_encode($options));

        $data = Cache::remember($cacheKey, 30, function () use ($stockCode, $options) {
            return $this->brokerSummary->getBrokerSummary($stockCode, $options);
        });

        if ($data === null) {
            Cache::forget($cacheKey);

            return response()->json([
                'error' => 'Gagal mengambil data broker summary. Coba lagi sebentar lagi.',
            ], 502);
        }

        return response()->json($data);
    }

    /**
     * GET /broker-flow/{stockCode}
     * Query: dates (comma-separated YYYY-MM-DD), mode (value|volume)
     */
    public function flow(Request $request, string $stockCode)
    {
        $stockCode = strtoupper($stockCode);

        $datesParam = (string) $request->query('dates', '');
        $dates = array_values(array_filter(array_map('trim', explode(',', $datesParam))));

        $mode = $request->query('mode', 'value');
        $mode = in_array($mode, ['value', 'volume'], true) ? $mode : 'value';

        if (empty($dates)) {
            return response()->json(['brokers' => []]);
        }

        $cacheKey = 'broker_flow_' . $stockCode . '_' . $mode . '_' . md5(implode(',', $dates));

        $data = Cache::remember($cacheKey, 30, function () use ($stockCode, $dates, $mode) {
            return $this->brokerSummary->getBrokerFlow($stockCode, $dates, $mode);
        });

        if ($data === null) {
            Cache::forget($cacheKey);

            return response()->json([
                'error' => 'Gagal mengambil data broker flow. Coba lagi sebentar lagi.',
            ], 502);
        }

        return response()->json($data);
    }
}
