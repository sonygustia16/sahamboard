@extends('layouts.app')

@section('title', 'Entry Plan')
@section('page-title', 'Entry Plan')
@section('page-subtitle', 'Rencanakan dan catat titik entry untuk saham target Anda')

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Rencana Entry Baru</h3>
        <form action="{{ route('entry.store') }}" method="POST">
            @csrf
            <div class="filter-grid">
                <div class="form-group">
                    <label>Stock Code</label>
                    <input type="text" name="stock_code" placeholder="Contoh: BBCA" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Target Entry Price</label>
                    <input type="number" step="0.01" name="entry_price" placeholder="0" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Stop Loss</label>
                    <input type="number" step="0.01" name="stop_loss" placeholder="0" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Take Profit</label>
                    <input type="number" step="0.01" name="take_profit" placeholder="0" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Rencana Tanggal</label>
                    <input type="date" name="plan_date">
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Simpan Rencana</button>
                <a href="{{ route('entry.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card"><div class="kpi-label">Total Rencana Aktif</div><div class="kpi-value">{{ $totalActive }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Rata-rata Risk : Reward</div><div class="kpi-value">{{ $avgRR ?? '—' }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Entry Tercapai</div><div class="kpi-value green">{{ $entryTercapai }}</div></div>
        <div class="kpi-card"><div class="kpi-label">Stop Loss Tersentuh</div><div class="kpi-value red">{{ $stopLossTersentuh }}</div></div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Stock Code</th>
                    <th>Entry</th>
                    <th>Stop Loss</th>
                    <th>Take Profit</th>
                    <th>R:R</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($plans as $plan)
                    <tr>
                        <td class="text-center"><strong>{{ $loop->iteration }}</strong></td>
                        <td><span class="code-pill">{{ $plan->stock_code }}</span></td>
                        <td class="text-right">{{ number_format($plan->entry_price, 0, ',', '.') }}</td>
                        <td class="text-right text-red">{{ number_format($plan->stop_loss, 0, ',', '.') }}</td>
                        <td class="text-right text-green">{{ number_format($plan->take_profit, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $plan->riskRewardRatio() ?? '-' }}</td>
                        <td>{{ $plan->plan_date ? \Illuminate\Support\Carbon::parse($plan->plan_date)->format('d M y') : '-' }}</td>
                        <td class="text-center"><span class="status-chip">{{ str_replace('_', ' ', $plan->status) }}</span></td>
                        <td class="text-center">
                            <form action="{{ route('entry.destroy', $plan) }}" method="POST" onsubmit="return confirm('Hapus rencana ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.7rem; font-size:0.75rem;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="9">Belum ada rencana entry — tambahkan lewat form di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection
