<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;

class StockFilterController extends Controller
{
    protected YahooFinanceService $yahoo;

    public function __construct(YahooFinanceService $yahoo)
    {
        $this->yahoo = $yahoo;
    }

    /**
     * Screen 1: Filter Lengkap (dulu index.php)
     */
    public function index(Request $request)
    {
        $stockCode   = $request->query('stock_code', '');
        $startDate   = $request->query('start_date', '');
        $finishDate  = $request->query('finish_date', '');

        $filterPrevious  = $this->cleanNumber($request->query('previous', ''));
        $filterFrequency = $this->cleanNumber($request->query('frequency', ''));
        $filterValue     = $this->cleanNumber($request->query('value', ''));

        $opPrevious  = $request->query('op_previous', '=');
        $opFrequency = $request->query('op_frequency', '=');
        $opValue     = $request->query('op_value', '=');

        $isSearching = $stockCode != ''
            || ($startDate != '' && $finishDate != '')
            || $filterPrevious != ''
            || $filterFrequency != ''
            || $filterValue != '';

        $query = RingkasanSaham::query()
            ->stockCode($stockCode)
            ->dateRange($startDate, $finishDate)
            ->numericFilter('previous', $filterPrevious, $opPrevious)
            ->numericFilter('frequency', $filterFrequency, $opFrequency)
            ->numericFilter('value', $filterValue, $opValue);

        // Limit disesuaikan dengan jenis pencarian:
        // - Tanpa filter sama sekali -> 15 (default, biar ringan)
        // - Ada filter periode tanggal (start_date & finish_date) -> 300
        //   (PENTING: kalau limit terlalu kecil, tanggal terbaru bisa "menghabiskan"
        //   semua slot duluan karena ORDER BY date DESC, jadi tanggal yang lebih lama
        //   di dalam periode yang sama bisa tidak muncul sama sekali walau datanya ada)
        // - Filter lain saja (stock code / previous / frequency / value) -> 50
        $hasDateRange = $startDate != '' && $finishDate != '';

        if ($hasDateRange) {
            $limit = 300;
        } elseif ($isSearching) {
            $limit = 50;
        } else {
            $limit = 15;
        }

        $rows = $query->orderBy('date', 'desc')
            ->orderBy('value', 'desc')
            ->limit($limit)
            ->get();

        // Ambil harga live, di-cache per kode saham (sama seperti perilaku lama)
        $stockCodes = $rows->pluck('stock_code')->all();
        $livePriceCache = $this->yahoo->getLivePrices($stockCodes, 1);

        return view('screens.index', [
            'rows'           => $rows,
            'livePriceCache' => $livePriceCache,
            'stockCode'      => $stockCode,
            'startDate'      => $startDate,
            'finishDate'     => $finishDate,
            'filterPrevious' => $request->query('previous', ''),
            'filterFrequency'=> $request->query('frequency', ''),
            'filterValue'    => $request->query('value', ''),
            'opPrevious'     => $opPrevious,
            'opFrequency'    => $opFrequency,
            'opValue'        => $opValue,
        ]);
    }

    private function cleanNumber($val)
    {
        return str_replace('.', '', (string) $val);
    }
}