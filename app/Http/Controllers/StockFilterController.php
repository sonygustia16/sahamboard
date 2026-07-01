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

        // Inisialisasi awal query dasar
        $query = RingkasanSaham::query();

        // 1. Jalankan filter stock_code HANYA jika ada input teksnya
        if (!empty($stockCode)) {
            $query->where('stock_code', $stockCode);
        }

        // 2. Jalankan filter tanggal HANYA jika start dan finish diisi
        if (!empty($startDate) && !empty($finishDate)) {
            $query->whereBetween('date', [$startDate, $finishDate]);
        }

        // 3. Jalankan filter previous HANYA jika ada angkanya
        if ($filterPrevious !== '') {
            $query->where('previous', $opPrevious, $filterPrevious);
        }

        // 4. Jalankan filter frequency HANYA jika ada angkanya
        if ($filterFrequency !== '') {
            $query->where('frequency', $opFrequency, $filterFrequency);
        }

        // 5. Jalankan filter value HANYA jika ada angkanya
        if ($filterValue !== '') {
            $query->where('value', $opValue, $filterValue);
        }

        // Atur limit data agar tidak lemot
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

        // Ambil harga live, di-cache per kode saham
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