<?php

namespace App\Http\Controllers;

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
     * Kalau saham belum ada di watchlist -> ditambahkan (target_price default = harga live saat itu, bisa diedit lagi di halaman Watchlist).
     * Kalau sudah ada -> dihapus. Dipanggil via fetch() AJAX, tidak reload halaman.
     */
    public function quickToggle(Request $request)
    {
        $validated = $request->validate([
            'stock_code' => 'required|string|max:10',
            'live_price' => 'nullable|numeric|min:0',
        ]);

        $stockCode = strtoupper($validated['stock_code']);
        $existing = Watchlist::where('stock_code', $stockCode)->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'added' => false]);
        }

        Watchlist::create([
            'stock_code'   => $stockCode,
            'target_price' => $validated['live_price'] ?? 0,
        ]);

        return response()->json(['success' => true, 'added' => true]);
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
}
