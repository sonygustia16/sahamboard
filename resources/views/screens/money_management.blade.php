@extends('layouts.app')

@section('title', 'Money Management')
@section('page-title', 'Money Management')
@section('page-subtitle', 'Pantau alokasi portofolio dan eksposur risiko keseluruhan')

@section('content')

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Modal</div>
            <div class="kpi-value">Rp {{ number_format($totalCapital, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Dana Teralokasi</div>
            <div class="kpi-value">Rp {{ number_format($totalAllocated, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Cash Tersisa</div>
            <div class="kpi-value green">Rp {{ number_format($totalCash, 0, ',', '.') }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Jumlah Posisi Terbuka</div>
            <div class="kpi-value">{{ $holdings->count() }}</div>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Atur Alokasi Modal</h3>
        <form action="{{ route('money-management.store') }}" method="POST">
            @csrf
            <div class="filter-grid">
                <div class="form-group">
                    <label>Total Modal (Rp)</label>
                    <input type="number" step="0.01" name="total_capital" value="{{ $setting->total_capital }}" placeholder="50000000" style="width:160px;" required>
                </div>
                <div class="form-group">
                    <label>Maks. Risiko per Saham (%)</label>
                    <input type="number" step="0.01" name="max_risk_per_stock" value="{{ $setting->max_risk_per_stock }}" placeholder="5" style="width:140px;" required>
                </div>
                <div class="form-group">
                    <label>Maks. Posisi Bersamaan</label>
                    <input type="number" name="max_positions" value="{{ $setting->max_positions }}" placeholder="6" style="width:140px;" required>
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Simpan Setting</button>
            </div>
        </form>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Tambah Alokasi Saham</h3>
        <form action="{{ route('money-management.holding.store') }}" method="POST">
            @csrf
            <div class="filter-grid">
                <div class="form-group">
                    <label>Stock Code</label>
                    <input type="text" name="stock_code" placeholder="Contoh: BBCA" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Alokasi (Rp)</label>
                    <input type="number" step="0.01" name="allocation" placeholder="0" style="width:150px;" required>
                </div>
                <div class="form-group">
                    <label>Unrealized P/L (Rp)</label>
                    <input type="number" step="0.01" name="pnl" placeholder="0" style="width:150px;">
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Tambah Alokasi</button>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Stock Code</th>
                    <th>Alokasi (Rp)</th>
                    <th>Bobot Portofolio</th>
                    <th>Unrealized P/L</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($holdings as $h)
                    <tr>
                        <td class="text-center"><strong>{{ $loop->iteration }}</strong></td>
                        <td><span class="code-pill">{{ $h->stock_code }}</span></td>
                        <td class="text-right">Rp {{ number_format($h->allocation, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($h->weight, 1) }}%</td>
                        <td class="text-right {{ $h->pnl >= 0 ? 'text-green' : 'text-red' }}">
                            {{ ($h->pnl >= 0 ? '+' : '') . number_format($h->pnl, 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            <form action="{{ route('money-management.holding.destroy', $h) }}" method="POST" onsubmit="return confirm('Hapus alokasi ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.7rem; font-size:0.75rem;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6">Belum ada data alokasi — tambahkan lewat form di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection
