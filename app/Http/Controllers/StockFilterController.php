<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Models\SavedFilter;
use App\Models\Watchlist;
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
                'watchlistedCodes' => Watchlist::pluck('stock_code')->map(fn ($c) => strtoupper($c))->all(),
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
            'watchlistedCodes' => Watchlist::pluck('stock_code')->map(fn ($c) => strtoupper($c))->all(),
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

        // Ambil SEMUA histori yang ada untuk saham ini (bukan cuma warm-up terbatas),
        // supaya perhitungan RSI/Stochastic RSI/MACD didasarkan pada seluruh data historis
        // yang tersedia — sama seperti Stockbit/TradingView, yang menghitung indikator dari
        // seluruh histori harga yang mereka punya, bukan cuma jendela pendek sebelum tampilan.
        $rows = RingkasanSaham::query()
            ->where('stock_code', $stockCode)
            ->orderBy('date', 'asc')
            ->get(['date', 'value', 'close']);

        $closesAll = $rows->map(fn ($r) => (float) $r->close)->values()->all();

        $rsiAll = $this->calcRsi($closesAll, 14);
        [$stochKAll, $stochDAll] = $this->calcStochRsi($closesAll, 14, 14, 3, 3);
        [$macdLineAll, $macdSignalAll, $macdHistAll] = $this->calcMacd($closesAll, 12, 26, 9);

        // Potong lagi cuma bagian yang mau ditampilkan (buang periode warm-up)
        $cutoffDate = now()->subDays($days)->toDateString();
        $displayRows = $rows->filter(fn ($r) => $r->date >= $cutoffDate)->values();
        $startIndex = $rows->count() - $displayRows->count();

        $slice = fn ($arr) => array_values(array_slice($arr, $startIndex));

        return response()->json([
            'stock_code'   => $stockCode,
            'timeframe'    => $timeframe,
            'labels'       => $displayRows->map(fn ($r) => \Illuminate\Support\Carbon::parse($r->date)->format('d M y'))->all(),
            'values'       => $displayRows->map(fn ($r) => (float) $r->value)->all(),
            'closes'       => $displayRows->map(fn ($r) => (float) $r->close)->all(),
            'rsi'          => $slice($rsiAll),
            'stoch_k'      => $slice($stochKAll),
            'stoch_d'      => $slice($stochDAll),
            'macd_line'    => $slice($macdLineAll),
            'macd_signal'  => $slice($macdSignalAll),
            'macd_hist'    => $slice($macdHistAll),
        ]);
    }

    /**
     * RSI (Relative Strength Index) standar Wilder, period 14 default.
     * Return array sepanjang $closes, isinya null di titik-titik awal yang belum cukup data.
     */
    private function calcRsi(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $rsi = array_fill(0, $n, null);
        if ($n < $period + 1) {
            return $rsi;
        }

        $gains = 0; $losses = 0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            if ($diff > 0) $gains += $diff; else $losses += abs($diff);
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;
        $rsi[$period] = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));

        for ($i = $period + 1; $i < $n; $i++) {
            $diff = $closes[$i] - $closes[$i - 1];
            $gain = $diff > 0 ? $diff : 0;
            $loss = $diff < 0 ? abs($diff) : 0;
            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
            $rsi[$i] = $avgLoss == 0 ? 100 : 100 - (100 / (1 + ($avgGain / $avgLoss)));
        }

        return $rsi;
    }

    /**
     * Stochastic RSI: terapkan rumus stochastic ke deretan nilai RSI (bukan ke harga langsung),
     * lalu %K di-smooth (SMA) dan %D adalah SMA dari %K. Skala hasil 0-100.
     */
    private function calcStochRsi(array $closes, int $rsiPeriod = 14, int $stochPeriod = 14, int $smoothK = 3, int $smoothD = 3): array
    {
        $rsi = $this->calcRsi($closes, $rsiPeriod);
        $n = count($rsi);
        $rawK = array_fill(0, $n, null);

        for ($i = 0; $i < $n; $i++) {
            if ($rsi[$i] === null) continue;
            $windowStart = max(0, $i - $stochPeriod + 1);
            $window = array_filter(array_slice($rsi, $windowStart, $i - $windowStart + 1), fn ($v) => $v !== null);
            if (count($window) < $stochPeriod) continue;

            $minRsi = min($window);
            $maxRsi = max($window);
            $rawK[$i] = ($maxRsi - $minRsi) == 0 ? 0 : (($rsi[$i] - $minRsi) / ($maxRsi - $minRsi)) * 100;
        }

        $kSmoothed = $this->simpleMovingAverage($rawK, $smoothK);
        $dSmoothed = $this->simpleMovingAverage($kSmoothed, $smoothD);

        return [$kSmoothed, $dSmoothed];
    }

    /** SMA yang toleran terhadap null (hasil null kalau jendelanya belum penuh nilai valid) */
    private function simpleMovingAverage(array $arr, int $period): array
    {
        $n = count($arr);
        $out = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            $windowStart = max(0, $i - $period + 1);
            $window = array_filter(array_slice($arr, $windowStart, $i - $windowStart + 1), fn ($v) => $v !== null);
            if (count($window) < $period) continue;
            $out[$i] = array_sum($window) / count($window);
        }
        return $out;
    }

    /** EMA (Exponential Moving Average) standar */
    private function exponentialMovingAverage(array $closes, int $period): array
    {
        $n = count($closes);
        $ema = array_fill(0, $n, null);
        if ($n < $period) return $ema;

        $multiplier = 2 / ($period + 1);
        $sma = array_sum(array_slice($closes, 0, $period)) / $period;
        $ema[$period - 1] = $sma;

        for ($i = $period; $i < $n; $i++) {
            $ema[$i] = (($closes[$i] - $ema[$i - 1]) * $multiplier) + $ema[$i - 1];
        }

        return $ema;
    }

    /**
     * MACD standar (12, 26, 9): garis MACD = EMA12 - EMA26, garis Signal = EMA9 dari garis MACD,
     * Histogram = MACD - Signal.
     */
    private function calcMacd(array $closes, int $fast = 12, int $slow = 26, int $signalPeriod = 9): array
    {
        $n = count($closes);
        $emaFast = $this->exponentialMovingAverage($closes, $fast);
        $emaSlow = $this->exponentialMovingAverage($closes, $slow);

        $macdLine = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
                $macdLine[$i] = $emaFast[$i] - $emaSlow[$i];
            }
        }

        // EMA dari macdLine, cuma dihitung dari titik pertama macdLine yang valid
        $firstValidIndex = null;
        foreach ($macdLine as $i => $v) {
            if ($v !== null) { $firstValidIndex = $i; break; }
        }

        $macdSignal = array_fill(0, $n, null);
        if ($firstValidIndex !== null) {
            $validMacd = array_slice($macdLine, $firstValidIndex);
            $signalOnValid = $this->exponentialMovingAverage($validMacd, $signalPeriod);
            foreach ($signalOnValid as $offset => $v) {
                $macdSignal[$firstValidIndex + $offset] = $v;
            }
        }

        $macdHist = array_fill(0, $n, null);
        for ($i = 0; $i < $n; $i++) {
            if ($macdLine[$i] !== null && $macdSignal[$i] !== null) {
                $macdHist[$i] = $macdLine[$i] - $macdSignal[$i];
            }
        }

        return [$macdLine, $macdSignal, $macdHist];
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