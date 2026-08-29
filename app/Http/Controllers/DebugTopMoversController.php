<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\BrokerSummaryService;
use Illuminate\Support\Facades\DB;

/**
 * CONTROLLER SEMENTARA UNTUK DEBUG — hapus file ini + route-nya setelah selesai dipakai.
 */
class DebugTopMoversController extends Controller
{
    public function index(BrokerSummaryService $broker)
    {
        $latestDate = RingkasanSaham::max('date');

        // 1) Sample harga saham teraktif (by value)
        $sample = RingkasanSaham::where('date', $latestDate)
            ->orderByDesc('value')
            ->take(5)
            ->get(['stock_code', 'close', 'previous', 'value', 'date']);

        // 2) PERSIS query yang dipakai topMovers() di TopMoversController — supaya
        //    kelihatan apakah ORDER BY change_pct beneran jalan atau tidak.
        $topGainerRaw = RingkasanSaham::query()
            ->where('date', $latestDate)
            ->where('previous', '>', 0)
            ->selectRaw('stock_code, close, previous, ((close - previous) / previous) * 100 as change_pct')
            ->orderBy('change_pct', 'desc')
            ->limit(5)
            ->get();

        // 3) Statistik: berapa banyak baris yang close == previous (persis sama) di
        //    tanggal ini, dibanding total baris tanggal itu.
        $totalRows = RingkasanSaham::where('date', $latestDate)->count();
        $flatRows  = RingkasanSaham::where('date', $latestDate)
            ->whereColumn('close', '=', 'previous')
            ->count();
        $zeroPrevRows = RingkasanSaham::where('date', $latestDate)
            ->where(function ($q) { $q->whereNull('previous')->orWhere('previous', 0); })
            ->count();

        // 4) Cek juga BBCA secara spesifik (yang kita tahu dari sample close != previous)
        $bbca = RingkasanSaham::where('date', $latestDate)
            ->where('stock_code', 'BBCA')
            ->selectRaw('stock_code, close, previous, ((close - previous) / previous) * 100 as change_pct')
            ->first();

        // 5) Cek broker summary untuk 1 saham teraktif
        $topStock = RingkasanSaham::where('date', $latestDate)
            ->orderByDesc('value')
            ->first(['stock_code']);

        $brokerResult = null;
        $brokerError = null;
        if ($topStock) {
            try {
                $brokerResult = $broker->getBrokerSummary($topStock->stock_code, [
                    'start_date' => $latestDate,
                    'end_date'   => $latestDate,
                    'all_data'   => true,
                ]);
            } catch (\Throwable $e) {
                $brokerError = $e->getMessage();
            }
        }

        return response()->json([
            'app_version_marker'  => 'debug-v2-with-order-by-check',
            'latest_date'         => $latestDate,
            'total_rows_this_date'=> $totalRows,
            'flat_rows_close_eq_previous' => $flatRows,
            'zero_or_null_previous_rows'  => $zeroPrevRows,
            'sample_by_value'     => $sample,
            'top_gainer_query_result' => $topGainerRaw,
            'bbca_check'          => $bbca,
            'tested_stock_code'   => $topStock->stock_code ?? null,
            'broker_api_key_set'  => !empty(config('services.broker_summary.api_key')),
            'broker_result_summary' => $brokerResult ? [
                'stock_code' => $brokerResult['stock_code'] ?? null,
                'broker_count' => count($brokerResult['brokers'] ?? []),
                'total_nval' => array_sum(array_map(fn($b) => (float)($b['nval'] ?? 0), $brokerResult['brokers'] ?? [])),
            ] : null,
            'broker_error'        => $brokerError,
        ], 200, [], JSON_PRETTY_PRINT);
    }
}
