<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Models\SavedFilter;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockFilterController extends Controller
{
    protected YahooFinanceService $yahoo;

    public function __construct(YahooFinanceService $yahoo)
    {
        $this->yahoo = $yahoo;
    }

    public function index(Request $request)
    {
        // Halaman Filter Lengkap: sengaja TIDAK mengaktifkan mode screening akumulasi,
        // supaya halaman ini tetap simpel & tidak terganggu logika screening.
        return $this->handleFilterRequest($request, false, 'screens.index');
    }

    /**
     * Halaman Screening (dulu "Analysis & Chart"): sama persis dengan Filter Lengkap,
     * tapi mendukung mode screening akumulasi (checkbox "Berpotensi Akumulasi").
     */
    public function screening(Request $request)
    {
        return $this->handleFilterRequest($request, true, 'screens.screening');
    }

    private function handleFilterRequest(Request $request, bool $allowScreening, string $viewName)
    {
        $stockCode   = $request->query('stock_code', '');
        $startDate   = $request->query('start_date', '');
        $finishDate  = $request->query('finish_date', '');
        $screening   = $allowScreening ? $request->query('screening', '') : '';

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
            || $filterValue != ''
            || $screening != '';

        if ($screening === 'akumulasi') {
            // ══ MODE SCREENING: Berpotensi Akumulasi ══
            // Cari saham yang, dibanding hari transaksi sebelumnya: Close turun TAPI Value NR naik.
            // Dihitung untuk tanggal terbaru yang ada di database (atau finish_date kalau diisi).
            $currentDate = !empty($finishDate)
                ? $finishDate
                : RingkasanSaham::max('date');

            $prevDatesSub = RingkasanSaham::query()
                ->selectRaw('stock_code, MAX(date) as prev_date')
                ->where('date', '<', $currentDate)
                ->groupBy('stock_code');

            $query = RingkasanSaham::query()
                ->from('ringkasan_saham as curr')
                ->joinSub($prevDatesSub, 'lp', function ($join) {
                    $join->on('lp.stock_code', '=', 'curr.stock_code');
                })
                ->join('ringkasan_saham as prev', function ($join) {
                    $join->on('prev.stock_code', '=', 'curr.stock_code')
                         ->on('prev.date', '=', 'lp.prev_date');
                })
                ->where('curr.date', $currentDate)
                // Threshold: Close turun min 1%, Value NR naik min 50%
                // (bukan cuma turun/naik dikit yang gampang keitung noise/data harian normal)
                ->whereRaw('curr.close < prev.close * 0.99')
                ->whereRaw('curr.value > prev.value * 1.5')
                ->select(
                    'curr.*',
                    DB::raw('(prev.close - curr.close) as close_drop'),
                    DB::raw('(curr.value - prev.value) as value_gain')
                );

            if (!empty($stockCode)) {
                $query->where('curr.stock_code', $stockCode);
            }

            $query->orderByDesc('value_gain');

            $perPage = 25;
            $rows = $query->paginate($perPage)->withQueryString();

            $stockCodes     = $rows->pluck('stock_code')->all();
            $livePriceCache = $this->yahoo->getLivePrices($stockCodes, 1);

            return view($viewName, [
                'rows'            => $rows,
                'livePriceCache'  => $livePriceCache,
                'stockCode'       => $stockCode,
                'startDate'       => $startDate,
                'finishDate'      => $finishDate,
                'filterPrevious'  => '',
                'filterFrequency' => '',
                'filterValue'     => '',
                'opPrevious'      => $opPrevious,
                'opFrequency'     => $opFrequency,
                'opValue'         => $opValue,
                'isSearching'     => $isSearching,
                'savedFilters'    => SavedFilter::orderBy('name')->get(),
                'screening'       => $screening,
                'screeningDate'   => $currentDate,
            ]);
        }

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

        return view($viewName, [
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
            'savedFilters'    => SavedFilter::orderBy('name')->get(),
            'screening'       => '',
            'screeningDate'   => null,
        ]);
    }

    /**
     * Simpan kombinasi filter (previous/frequency/value + operator) sebagai preset
     * bernama, supaya bisa dipanggil ulang tanpa isi ulang form tiap login.
     */
    public function storePreset(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:100',
            'op_previous'  => 'nullable|in:=,!=,<,<=,>,>=',
            'previous'     => 'nullable|string',
            'op_frequency' => 'nullable|in:=,!=,<,<=,>,>=',
            'frequency'    => 'nullable|string',
            'op_value'     => 'nullable|in:=,!=,<,<=,>,>=',
            'value'        => 'nullable|string',
        ]);

        SavedFilter::create([
            'name'         => $validated['name'],
            'op_previous'  => $validated['op_previous'] ?? '=',
            'previous'     => $this->cleanNumber($validated['previous'] ?? '') ?: null,
            'op_frequency' => $validated['op_frequency'] ?? '=',
            'frequency'    => $this->cleanNumber($validated['frequency'] ?? '') ?: null,
            'op_value'     => $validated['op_value'] ?? '=',
            'value'        => $this->cleanNumber($validated['value'] ?? '') ?: null,
        ]);

        return redirect()->back()->with('success', 'Preset filter tersimpan.');
    }

    public function destroyPreset(SavedFilter $savedFilter)
    {
        $savedFilter->delete();
        return redirect()->back()->with('success', 'Preset filter dihapus.');
    }

    /**
     * Endpoint JSON ringan untuk chart klik-langsung di tabel filter.
     * Dipanggil via fetch() dari JS, bukan reload halaman.
     * Data selalu dari tabel ringkasan_saham (database), bukan hardcode.
     * Sekarang kirim 2 seri: value (Value NR) dan close (Close Price).
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
            ->get(['date', 'value', 'close']);

        return response()->json([
            'stock_code' => $stockCode,
            'timeframe'  => $timeframe,
            'labels'     => $rows->map(fn ($r) => \Illuminate\Support\Carbon::parse($r->date)->format('d M y'))->all(),
            'values'     => $rows->map(fn ($r) => (float) $r->value)->all(),
            'closes'     => $rows->map(fn ($r) => (float) $r->close)->all(),
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