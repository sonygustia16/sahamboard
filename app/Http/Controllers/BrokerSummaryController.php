<?php

namespace App\Http\Controllers;

use App\Services\BrokerSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;   // ← tambahkan use ini

class BrokerSummaryController extends Controller
{
    protected BrokerSummaryService $brokerSummary;

    public function __construct(BrokerSummaryService $brokerSummary)
    {
        $this->brokerSummary = $brokerSummary;
    }

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

        // ── DEBUG SEMENTARA: lihat bentuk asli data dari API ──
        Log::info('DEBUG broker-summary response shape', [
            'keys' => is_array($data) ? array_keys($data) : gettype($data),
            'sample' => json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR) 
                ? substr(json_encode($data), 0, 1500) 
                : 'unencodable',
        ]);
        // ── akhir debug ──

        return response()->json($data);
    }

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