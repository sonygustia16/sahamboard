@extends('layouts.app')

@section('title', 'Top Movers')
@section('page-title', 'Top Movers')
@section('page-subtitle', $period === 'weekly'
    ? 'Akumulasi broker ' . \Illuminate\Support\Carbon::parse($startDate)->format('d M') . ' – ' . \Illuminate\Support\Carbon::parse($date)->format('d M Y')
    : 'Snapshot broker harian · ' . \Illuminate\Support\Carbon::parse($date)->format('d M Y') . ($date !== $latestDate ? ' (data historis)' : ''))

@section('content')

<div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1.2rem;">
    <div style="display:flex; gap:0.5rem;">
        <a href="{{ route('top-movers.index', array_merge(request()->query(), ['period' => 'daily'])) }}"
           class="btn {{ $period === 'daily' ? 'btn-primary' : 'btn-ghost' }}">Harian</a>
        <a href="{{ route('top-movers.index', array_merge(request()->query(), ['period' => 'weekly'])) }}"
           class="btn {{ $period === 'weekly' ? 'btn-primary' : 'btn-ghost' }}">Mingguan (5 Hari)</a>
    </div>

    <form method="GET" action="{{ route('top-movers.index') }}" style="display:flex; gap:0.5rem; align-items:center;">
        <input type="hidden" name="period" value="{{ $period }}">
        <label style="color:var(--muted); font-size:0.8rem;">Tanggal:</label>
        <input type="date" name="date" value="{{ $date }}" max="{{ $latestDate }}"
               onchange="this.form.submit()"
               style="background:var(--panel-2); border:1px solid var(--border); color:var(--ink); border-radius:6px; padding:0.35rem 0.6rem; font-family:var(--mono); font-size:0.8rem;">
        @if($date !== $latestDate)
            <a href="{{ route('top-movers.index', ['period' => $period]) }}" class="btn btn-ghost" style="padding:0.35rem 0.7rem; font-size:0.75rem;">Ke Terbaru</a>
        @endif
    </form>
</div>

<div class="top-movers-grid">

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Gainer</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Price</th><th class="text-right">Change (%)</th></tr></thead>
                <tbody>
                    @forelse ($topGainer as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->close, 0, ',', '.') }}</td>
                            <td class="text-right text-green">{{ number_format($row->change_pct, 2) }}%</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="3">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Loser</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Price</th><th class="text-right">Change (%)</th></tr></thead>
                <tbody>
                    @forelse ($topLoser as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->close, 0, ',', '.') }}</td>
                            <td class="text-right text-red">{{ number_format($row->change_pct, 2) }}%</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="3">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Akum (Broker Net Buy)</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Price</th><th class="text-right">Change (%)</th><th class="text-right">Acc/Dist</th></tr></thead>
                <tbody>
                    @forelse ($topAkum as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->close, 0, ',', '.') }}</td>
                            <td class="text-right {{ $row->change_pct >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($row->change_pct, 2) }}%</td>
                            <td class="text-right"><span class="acc-badge acc-badge-buy">{{ $row->label }}</span></td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="4">Tidak ada sinyal akumulasi terdeteksi pada saham teraktif periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Dist (Broker Net Sell)</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Price</th><th class="text-right">Change (%)</th><th class="text-right">Acc/Dist</th></tr></thead>
                <tbody>
                    @forelse ($topDist as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->close, 0, ',', '.') }}</td>
                            <td class="text-right {{ $row->change_pct >= 0 ? 'text-green' : 'text-red' }}">{{ number_format($row->change_pct, 2) }}%</td>
                            <td class="text-right"><span class="acc-badge acc-badge-sell">{{ $row->label }}</span></td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="4">Tidak ada sinyal distribusi terdeteksi pada saham teraktif periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<p style="color:var(--muted); font-size:0.72rem; margin-top:1rem;">
    * Dihitung live dari data broker summary untuk saham-saham paling aktif (by value transaksi) pada tanggal terakhir. Hasil di-cache 3 jam untuk menghemat kuota API.
</p>

@endsection

@push('body-scripts')
<style>
    .top-movers-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
    @media (max-width: 900px) { .top-movers-grid { grid-template-columns: 1fr; } }
    .acc-badge {
        font-family: var(--mono); font-size: 0.7rem; font-weight: 700;
        padding: 0.15rem 0.5rem; border-radius: 999px; white-space: nowrap;
    }
    .acc-badge-buy  { color: #10b981; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.35); }
    .acc-badge-sell { color: var(--loss, #f43f5e); background: rgba(244,63,94,0.12); border: 1px solid rgba(244,63,94,0.35); }
</style>
@endpush
