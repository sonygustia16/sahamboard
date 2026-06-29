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
}
