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

    public function index(Request $request)
    {
        $stockCode   = $request->query('stock_code', '');
        $startDate   = $request->query('start_date', '');
        $finishDate  = $request->query('finish_date', '');

        $filterPrevious  = $this->cleanNumber($request->query('previous', ''));
        $filterFrequency = $this->cleanNumber($request->query('frequency', ''));
        $filterValue     = $this->cleanNumber($request->query('value', ''));

        $opPrevious  = $this->cleanOperator($request->query('op_previous', '='));
        $opFrequency = $this->cleanOperator($request->query('op_frequency', '='));
        $opValue     = $this->cleanOperator($request->query('op_value', '='));

        $isSearching = $stockCode != ''
            || ($startDate != '' && $finishDate != '')
            || $filterPrevious != ''
            || $filterFrequency != ''
            || $filterValue != '';

        // Inisialisasi query (pertahankan logika filter kamu)
        $query = RingkasanSaham::query();

        if (!empty($stockCode)) {
            $query->where('stock_code', $stockCode);
        }

        if (!empty($startDate) && !empty($finishDate)) {
            $query->whereBetween('date', [$startDate, $finishDate]);
        }

        if ($filterPrevious !== '') {
            $query->where('previous', $opPrevious, $filterPrevious);
        }

        if ($filterFrequency !== '') {
            $query->where('frequency', $opFrequency, $filterFrequency);
        }

        if ($filterValue !== '') {
            $query->where('value', $opValue, $filterValue);
        }

        $query->orderBy('date', 'desc')->orderBy('value', 'desc');

        // Jumlah baris per halaman
        $perPage = $isSearching ? 25 : 15;

        // paginate() menggantikan limit()->get()
        // withQueryString() memastikan filter tetap terbawa saat pindah halaman
        $rows = $query->paginate($perPage)->withQueryString();

        // Fetch harga live hanya untuk baris di halaman aktif saja (lebih ringan)
        $stockCodes     = $rows->pluck('stock_code')->all();
        $livePriceCache = $this->yahoo->getLivePrices($stockCodes, 1);

        return view('screens.index', [
            'rows'            => $rows,
            'livePriceCache'  => $livePriceCache,
            'stockCode'       => $stockCode,
            'startDate'       => $startDate,
            'finishDate'      => $finishDate,
            'filterPrevious'  => $request->query('previous', ''),
            'filterFrequency' => $request->query('frequency', ''),
            'filterValue'     => $request->query('value', ''),
            'opPrevious'      => $opPrevious,
            'opFrequency'     => $opFrequency,
            'opValue'         => $opValue,
            'isSearching'     => $isSearching,
        ]);
    }

    /**
     * Endpoint JSON ringan untuk chart klik-langsung di tabel filter.
     * Dipanggil via fetch() dari JS, bukan reload halaman.
     * Data selalu dari tabel ringkasan_saham (database), bukan hardcode.
     */
    public function chartData(Request $request, string $stockCode)
    {
        $stockCode = strtoupper($stockCode);
        $timeframe = $request->query('timeframe', '1m');

        $daysMap = ['7d' => 7, '1m' => 30, '3m' => 90, '6m' => 180, '1y' => 365];
        $days = $daysMap[$timeframe] ?? 30;

        $rows = RingkasanSaham::query()
            ->where('stock_code', $stockCode)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date', 'asc')
            ->get(['date', 'value']);

        return response()->json([
            'stock_code' => $stockCode,
            'timeframe'  => $timeframe,
            'labels'     => $rows->map(fn ($r) => \Illuminate\Support\Carbon::parse($r->date)->format('d M y'))->all(),
            'values'     => $rows->map(fn ($r) => (float) $r->value)->all(),
        ]);
    }

    private function cleanNumber($val)
    {
        return str_replace('.', '', (string) $val);
    }

    /**
     * Whitelist operator perbandingan untuk query filter (previous/frequency/value).
     * Meski Laravel query builder sudah punya proteksi internal untuk operator
     * tidak valid, kita tetap validasi eksplisit di sini sebagai defense in depth
     * dan supaya perilaku aplikasi jelas & terprediksi (bukan diam-diam fallback).
     */
    private function cleanOperator($op)
    {
        $allowed = ['=', '!=', '<', '<=', '>', '>='];
        return in_array($op, $allowed, true) ? $op : '=';
    }
}