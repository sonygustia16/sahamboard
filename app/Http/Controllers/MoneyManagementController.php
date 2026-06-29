<?php

namespace App\Http\Controllers;

use App\Models\MoneyManagementHolding;
use App\Models\MoneyManagementSetting;
use Illuminate\Http\Request;

class MoneyManagementController extends Controller
{
    public function index()
    {
        $setting = MoneyManagementSetting::current();
        $holdings = MoneyManagementHolding::orderBy('created_at', 'desc')->get();

        $totalCapital = (float) $setting->total_capital;
        $totalAllocated = (float) $holdings->sum('allocation');
        $totalCash = $totalCapital - $totalAllocated;

        // Hitung bobot portofolio (%) tiap holding, dipakai langsung di view
        $holdings = $holdings->map(function ($h) use ($totalCapital) {
            $h->weight = $totalCapital > 0 ? round(($h->allocation / $totalCapital) * 100, 1) : 0;
            return $h;
        });

        return view('screens.money_management', [
            'setting'        => $setting,
            'holdings'       => $holdings,
            'totalCapital'   => $totalCapital,
            'totalAllocated' => $totalAllocated,
            'totalCash'      => $totalCash,
        ]);
    }

    /** Simpan / update setting modal & risiko (single-row config) */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'total_capital'      => 'required|numeric|min:0',
            'max_risk_per_stock' => 'required|numeric|min:0|max:100',
            'max_positions'      => 'required|integer|min:0',
        ]);

        $setting = MoneyManagementSetting::current();
        $setting->update($validated);

        return redirect()->route('money-management.index')->with('success', 'Setting disimpan.');
    }

    /** Tambah satu holding/alokasi saham baru */
    public function storeHolding(Request $request)
    {
        $validated = $request->validate([
            'stock_code' => 'required|string|max:10',
            'allocation' => 'required|numeric|min:0',
            'pnl'        => 'nullable|numeric',
        ]);

        $validated['stock_code'] = strtoupper($validated['stock_code']);
        $validated['pnl'] = $validated['pnl'] ?? 0;

        MoneyManagementHolding::create($validated);

        return redirect()->route('money-management.index')->with('success', 'Alokasi saham ditambahkan.');
    }

    public function destroyHolding(MoneyManagementHolding $holding)
    {
        $holding->delete();
        return redirect()->route('money-management.index')->with('success', 'Alokasi dihapus.');
    }
}
