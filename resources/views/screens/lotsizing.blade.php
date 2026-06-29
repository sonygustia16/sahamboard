@extends('layouts.app')

@section('title', 'Lot Sizing')
@section('page-title', 'Lot Sizing Calculator')
@section('page-subtitle', 'Hitung jumlah lot ideal berdasarkan parameter risiko')

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Parameter Risiko</h3>
        <div class="filter-grid">
            <div class="form-group">
                <label>Modal Total (Rp)</label>
                <input type="text" id="capital" placeholder="10.000.000" style="width:150px;">
            </div>
            <div class="form-group">
                <label>Risiko per Trade (%)</label>
                <input type="text" id="riskPercent" placeholder="2" style="width:110px;">
            </div>
            <div class="form-group">
                <label>Harga Entry</label>
                <input type="text" id="entryPrice" placeholder="0" style="width:130px;">
            </div>
            <div class="form-group">
                <label>Harga Stop Loss</label>
                <input type="text" id="stopPrice" placeholder="0" style="width:130px;">
            </div>
        </div>
        <div class="action-row">
            <button type="button" class="btn btn-primary" onclick="calcLot()">Hitung Lot</button>
            <a href="{{ route('lotsizing.index') }}" class="btn btn-ghost">Reset</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">Risiko Nominal (Rp)</div><div class="kpi-value red" id="riskAmount">—</div></div>
        <div class="kpi-card"><div class="kpi-label">Saham per Risiko</div><div class="kpi-value" id="sharesQty">—</div></div>
        <div class="kpi-card"><div class="kpi-label">Jumlah Lot (1 lot = 100 lbr)</div><div class="kpi-value green" id="lotQty">—</div></div>
        <div class="kpi-card"><div class="kpi-label">Estimasi Modal Terpakai</div><div class="kpi-value" id="usedCapital">—</div></div>
    </div>

@endsection

@push('body-scripts')
<script>
function calcLot(){
    const capital = parseFloat(document.getElementById('capital').value.replace(/\D/g,'')) || 0;
    const riskPct = parseFloat(document.getElementById('riskPercent').value) || 0;
    const entry   = parseFloat(document.getElementById('entryPrice').value.replace(/\D/g,'')) || 0;
    const stop    = parseFloat(document.getElementById('stopPrice').value.replace(/\D/g,'')) || 0;

    const riskAmount = capital * (riskPct/100);
    const perShareRisk = Math.abs(entry - stop);
    const shares = perShareRisk > 0 ? Math.floor(riskAmount / perShareRisk) : 0;
    const lots = Math.floor(shares / 100);
    const usedCapital = lots * 100 * entry;

    document.getElementById('riskAmount').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(riskAmount);
    document.getElementById('sharesQty').innerText = new Intl.NumberFormat('id-ID').format(shares) + ' lbr';
    document.getElementById('lotQty').innerText = new Intl.NumberFormat('id-ID').format(lots) + ' lot';
    document.getElementById('usedCapital').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(usedCapital);
}
</script>
@endpush
