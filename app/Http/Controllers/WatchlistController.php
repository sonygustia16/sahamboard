<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Models\Watchlist;
use App\Services\YahooFinanceService;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    protected YahooFinanceService $yahoo;

    public function __construct(YahooFinanceService $yahoo)
    {
        $this->yahoo = $yahoo;
    }

    public function index()
    {
        $watchlistRows = Watchlist::orderBy('created_at', 'desc')->get();

        $codes = $watchlistRows->pluck('stock_code')->all();
        $livePriceCache = $this->yahoo->getLivePrices($codes, 1);

        return view('screens.watchlist', [
            'watchlistRows'  => $watchlistRows,
            'livePriceCache' => $livePriceCache,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stock_code'   => 'required|string|max:10',
            'target_price' => 'required|numeric|min:0',
            'note'         => 'nullable|string|max:255',
        ]);

        $validated['stock_code'] = strtoupper($validated['stock_code']);
        $validated['date'] = now()->toDateString();

        Watchlist::create($validated);

        return redirect()->route('watchlist.index')->with('success', 'Saham ditambahkan ke watchlist.');
    }

    public function destroy(Watchlist $watchlist)
    {
        $watchlist->delete();
        return redirect()->route('watchlist.index')->with('success', 'Saham dihapus dari watchlist.');
    }

    /**
     * Toggle cepat lewat ikon bintang di tabel Filter Lengkap/Screening.
     * Kalau saham belum ada di watchlist -> ditambahkan (target_price & entry default = harga live saat itu,
     * date = tanggal baris tabel yang di-klik, biar tau data ini diambil dari tanggal berapa).
     * Kalau sudah ada -> dihapus. Dipanggil via fetch() AJAX, tidak reload halaman.
     */
    public function quickToggle(Request $request)
    {
        $validated = $request->validate([
            'stock_code' => 'required|string|max:10',
            'live_price' => 'nullable|numeric|min:0',
            'date'       => 'nullable|date',
        ]);

        $stockCode = strtoupper($validated['stock_code']);
        $existing = Watchlist::where('stock_code', $stockCode)->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'added' => false]);
        }

        Watchlist::create([
            'stock_code'   => $stockCode,
            'date'         => $validated['date'] ?? now()->toDateString(),
            'target_price' => $validated['live_price'] ?? 0,
            'entry'        => $validated['live_price'] ?? 0,
        ]);

        return response()->json(['success' => true, 'added' => true]);
    }

    /**
     * Simpan perubahan dari modal detail Watchlist (Entry Lot, Target Price, Catatan).
     * Dipanggil via fetch() AJAX saat user klik "Simpan" di modal.
     */
    public function updateDetail(Request $request, Watchlist $watchlist)
    {
        $validated = $request->validate([
            'entry_lot'    => 'nullable|integer|min:0',
            'target_price' => 'nullable|numeric|min:0',
            'note'         => 'nullable|string|max:255',
            'fee_beli_pct' => 'nullable|numeric|min:0|max:10',
            'fee_jual_pct' => 'nullable|numeric|min:0|max:10',
        ]);

        $watchlist->update($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Endpoint ringan buat polling notifikasi target price dari halaman mana pun
     * (dipanggil via JS fetch tiap ~45 detik, lihat resources/views/layouts/app.blade.php).
     * Cuma balikin JSON, tidak render halaman — supaya cepat & tidak ganggu halaman lain.
     */
    public function alertsCheck()
    {
        $watchlistRows = Watchlist::all();

        if ($watchlistRows->isEmpty()) {
            return response()->json(['hit' => []]);
        }

        $codes = $watchlistRows->pluck('stock_code')->all();
        $livePriceCache = $this->yahoo->getLivePrices($codes, 1);

        $hit = [];
        foreach ($watchlistRows as $row) {
            $livePrice = $livePriceCache[$row->stock_code] ?? null;
            if ($livePrice !== null && $livePrice >= (float) $row->target_price) {
                $hit[] = [
                    'id'           => $row->id,
                    'stock_code'   => $row->stock_code,
                    'live_price'   => $livePrice,
                    'target_price' => (float) $row->target_price,
                ];
            }
        }

        return response()->json(['hit' => $hit]);
    }

    /**
     * Hitung Swing High / Swing Low + level Fibonacci Retracement untuk 1 saham
     * di watchlist. Dipanggil on-demand (fetch) saat modal detail dibuka —
     * bukan dihitung di halaman index, biar ringan.
     *
     * Cara kerja:
     * 1. Coba lookback 90 hari transaksi terakhir dulu (paling dekat/relevan).
     * 2. Swing High = titik 'high' tertinggi dalam window.
     * 3. Swing Low = titik 'low' terendah, tapi HARUS terjadi SETELAH swing high
     *    (merepresentasikan kaki turun dari puncak ke lembah).
     * 4. Kalau swing low tidak ketemu (misal swing high ada di ujung window,
     *    jadi tidak ada data sesudahnya) -> mundur ke window lebih lebar
     *    (180 hari, 365 hari, lalu seluruh histori) sampai ketemu.
     * 5. Kalau tetap tidak ketemu di seluruh histori yang ada -> return gagal
     *    dengan pesan, bukan angka asal-asalan.
     */
    public function fibonacci(Watchlist $watchlist)
    {
        $stockCode = $watchlist->stock_code;

        // Urutan window yang dicoba: paling dekat dulu, baru melebar ke belakang.
        $windowsToTry = [90, 180, 365, null]; // null = seluruh histori yang ada

        $swing = null;
        $windowUsed = null;

        foreach ($windowsToTry as $window) {
            $query = RingkasanSaham::where('stock_code', $stockCode)
                ->orderBy('date', 'desc');

            if ($window !== null) {
                $query->limit($window);
            }

            $rows = $query->get(['date', 'high', 'low'])
                ->sortBy('date')
                ->values();

            $swing = $this->findSwingHighLow($rows);

            if ($swing !== null) {
                $windowUsed = $window ?? 'all';
                break;
            }
        }

        if ($swing === null) {
            return response()->json([
                'success' => false,
                'message' => 'Data histori belum cukup untuk menghitung swing high/low saham ini.',
            ]);
        }

        $high = $swing['swing_high'];
        $low  = $swing['swing_low'];
        $diff = $high - $low;

        $levels = [
            '0.0'   => round($low, 2),
            '23.6'  => round($low + $diff * 0.236, 2),
            '38.2'  => round($low + $diff * 0.382, 2),
            '50.0'  => round($low + $diff * 0.5, 2),
            '61.8'  => round($low + $diff * 0.618, 2),
            '78.6'  => round($low + $diff * 0.786, 2),
            '100.0' => round($high, 2),
        ];

        return response()->json([
            'success'         => true,
            'stock_code'      => $stockCode,
            'swing_high'      => round($high, 2),
            'swing_high_date' => $swing['swing_high_date'],
            'swing_low'       => round($low, 2),
            'swing_low_date'  => $swing['swing_low_date'],
            'window_used'     => $windowUsed,
            'levels'          => $levels,
        ]);
    }

    /**
     * Cari swing high (titik 'high' tertinggi) lalu swing low (titik 'low'
     * terendah SETELAH swing high) dari koleksi baris (harus sudah urut
     * tanggal ASCENDING). Return null kalau tidak ketemu pasangan yang valid.
     */
    private function findSwingHighLow($rows): ?array
    {
        if ($rows->count() < 2) {
            return null;
        }

        $maxHighRow = null;
        $maxHighIdx = null;

        foreach ($rows as $i => $row) {
            if ($maxHighRow === null || (float) $row->high > (float) $maxHighRow->high) {
                $maxHighRow = $row;
                $maxHighIdx = $i;
            }
        }

        // Cari swing low di antara baris-baris SETELAH swing high saja.
        $afterHigh = $rows->slice($maxHighIdx + 1);

        if ($afterHigh->isEmpty()) {
            return null; // swing high ada di ujung window, tidak ada data sesudahnya
        }

        $minLowRow = null;

        foreach ($afterHigh as $row) {
            if ($minLowRow === null || (float) $row->low < (float) $minLowRow->low) {
                $minLowRow = $row;
            }
        }

        if ((float) $minLowRow->low >= (float) $maxHighRow->high) {
            return null; // data tidak wajar, lewati
        }

        return [
            'swing_high'      => (float) $maxHighRow->high,
            'swing_high_date' => \Illuminate\Support\Carbon::parse($maxHighRow->date)->format('d M y'),
            'swing_low'       => (float) $minLowRow->low,
            'swing_low_date'  => \Illuminate\Support\Carbon::parse($minLowRow->date)->format('d M y'),
        ];
    }
}
