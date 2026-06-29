<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;

class StockAnalysisController extends Controller
{
    protected YahooFinanceService $yahoo;

    public function __construct(YahooFinanceService $yahoo)
    {
        $this->yahoo = $yahoo;
    }

    public function index(Request $request)
    {
        $stockCode  = $request->query('stock_code', '');
        $startDate  = $request->query('start_date', '');
        $finishDate = $request->query('finish_date', '');

        $rows = RingkasanSaham::query()
            ->stockCode($stockCode)
            ->dateRange($startDate, $finishDate)
            ->orderBy('date', 'desc')
            ->limit(200)
            ->get();

        $chartRows = $rows->reverse()->values();

        $chartLabels = $chartRows->map(function ($row) {
            return date('d M y', strtotime($row->date));
        })->all();

        $chartValues = $chartRows->map(function ($row) {
            return (float) $row->value;
        })->all();

        $stockCodes = $rows->pluck('stock_code')->all();
        $livePriceCache = $this->yahoo->getLivePrices($stockCodes, 2);

        return view('screens.analysis', [
            'rows'           => $rows,
            'livePriceCache' => $livePriceCache,
            'chartLabels'    => $chartLabels,
            'chartValues'    => $chartValues,
            'stockCode'      => $stockCode,
            'startDate'      => $startDate,
            'finishDate'     => $finishDate,
        ]);
    }
}
