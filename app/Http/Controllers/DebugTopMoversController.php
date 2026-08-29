<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\BrokerSummaryService;

/**
 * CONTROLLER SEMENTARA UNTUK DEBUG — hapus file ini + route-nya setelah selesai dipakai.
 * Dibuat karena plan Render gratis tidak punya akses Shell/tinker.
 */
class DebugTopMoversController extends Controller
{
    public function index(BrokerSummaryService $broker)
    {
        $latestDate = RingkasanSaham::max('date');

        // 1) Cek data harga terbaru — apakah close vs previous benar-benar beda
        $sample = RingkasanSaham::where('date', $latestDate)
            ->orderByDesc('value')
            ->take(5)
            ->get(['stock_code', 'close', 'previous', 'value', 'date']);

        // 2) Cek pemanggilan broker summary untuk 1 saham teraktif
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
            'latest_date'        => $latestDate,
            'sample_ringkasan'   => $sample,
            'tested_stock_code'  => $topStock->stock_code ?? null,
            'broker_api_key_set' => !empty(config('services.broker_summary.api_key')),
            'broker_base_url'    => config('services.broker_summary.base_url'),
            'broker_result'      => $brokerResult,
            'broker_error'       => $brokerError,
        ], 200, [], JSON_PRETTY_PRINT);
    }
}
