<?php

namespace App\Http\Controllers;

use App\Models\EntryPlan;
use Illuminate\Http\Request;

class EntryPlanController extends Controller
{
    public function index()
    {
        $plans = EntryPlan::orderBy('plan_date', 'desc')->orderBy('id', 'desc')->get();

        $totalActive = $plans->where('status', 'active')->count();
        $entryTercapai = $plans->where('status', 'entry_tercapai')->count();
        $stopLossTersentuh = $plans->where('status', 'stop_loss_tersentuh')->count();

        // Rata-rata Risk:Reward dari semua rencana yang punya nilai valid
        $rrValues = $plans->map(fn ($p) => $p->riskRewardRatio())->filter();
        $avgRR = $rrValues->count() > 0 ? round($rrValues->avg(), 2) : null;

        return view('screens.entry', [
            'plans' => $plans,
            'totalActive' => $totalActive,
            'entryTercapai' => $entryTercapai,
            'stopLossTersentuh' => $stopLossTersentuh,
            'avgRR' => $avgRR,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'stock_code'   => 'required|string|max:10',
            'entry_price'  => 'required|numeric|min:0',
            'stop_loss'    => 'required|numeric|min:0',
            'take_profit'  => 'required|numeric|min:0',
            'plan_date'    => 'nullable|date',
        ]);

        $validated['stock_code'] = strtoupper($validated['stock_code']);

        EntryPlan::create($validated);

        return redirect()->route('entry.index')->with('success', 'Rencana entry disimpan.');
    }

    public function destroy(EntryPlan $entryPlan)
    {
        $entryPlan->delete();
        return redirect()->route('entry.index')->with('success', 'Rencana entry dihapus.');
    }
}
