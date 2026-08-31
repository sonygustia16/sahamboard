@extends('layouts.app')

@section('title', 'Top Movers')
@section('page-title', 'Top Movers')
@section('page-subtitle', 'Akumulasi broker ' . \Illuminate\Support\Carbon::parse($startDate)->format('d M Y') . ' – ' . \Illuminate\Support\Carbon::parse($endDate)->format('d M Y') . ($endDate !== $latestDate ? ' (data historis)' : ''))

@section('content')

<form method="GET" action="{{ route('top-movers.index') }}" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin-bottom:1.2rem;">
    <label style="color:var(--muted); font-size:0.8rem;">Dari:</label>
    <input type="date" name="start_date" value="{{ $startDate }}" max="{{ $latestDate }}"
           style="background:var(--panel-2); border:1px solid var(--border); color:var(--ink); border-radius:6px; padding:0.35rem 0.6rem; font-family:var(--mono); font-size:0.8rem;">

    <label style="color:var(--muted); font-size:0.8rem;">Sampai:</label>
    <input type="date" name="end_date" value="{{ $endDate }}" max="{{ $latestDate }}"
           style="background:var(--panel-2); border:1px solid var(--border); color:var(--ink); border-radius:6px; padding:0.35rem 0.6rem; font-family:var(--mono); font-size:0.8rem;">

    <button type="submit" class="btn btn-primary" style="padding:0.4rem 1rem; font-size:0.8rem;">Terapkan</button>

    @if($endDate !== $latestDate || $startDate !== \Illuminate\Support\Carbon::parse($latestDate)->subWeekdays(5)->toDateString())
        <a href="{{ route('top-movers.index') }}" class="btn btn-ghost" style="padding:0.4rem 0.8rem; font-size:0.75rem;">Reset ke Terbaru</a>
    @endif
</form>

<div class="top-movers-grid">

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Akum (Broker Net Buy)</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Previous</th><th class="text-right">Live Price</th><th class="text-right">Change</th><th class="text-right">Acc/Dist</th></tr></thead>
                <tbody>
                    @forelse ($topAkum as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->previous, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row->live_price, 0, ',', '.') }}</td>
                            <td class="text-right {{ $row->change_pct >= 0 ? 'text-green' : 'text-red' }}">
                                {{ $row->change_pct >= 0 ? '+' : '' }}{{ number_format($row->diff, 0, ',', '.') }} / {{ $row->change_pct >= 0 ? '+' : '' }}{{ number_format($row->change_pct, 2) }}%
                            </td>
                            <td class="text-right"><span class="acc-badge acc-badge-buy">{{ $row->label }}</span></td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="5">Tidak ada sinyal akumulasi terdeteksi pada saham teraktif periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Dist (Broker Net Sell)</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Previous</th><th class="text-right">Live Price</th><th class="text-right">Change</th><th class="text-right">Acc/Dist</th></tr></thead>
                <tbody>
                    @forelse ($topDist as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->previous, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row->live_price, 0, ',', '.') }}</td>
                            <td class="text-right {{ $row->change_pct >= 0 ? 'text-green' : 'text-red' }}">
                                {{ $row->change_pct >= 0 ? '+' : '' }}{{ number_format($row->diff, 0, ',', '.') }} / {{ $row->change_pct >= 0 ? '+' : '' }}{{ number_format($row->change_pct, 2) }}%
                            </td>
                            <td class="text-right"><span class="acc-badge acc-badge-sell">{{ $row->label }}</span></td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="5">Tidak ada sinyal distribusi terdeteksi pada saham teraktif periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Gainer</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Previous</th><th class="text-right">Live Price</th><th class="text-right">Change</th></tr></thead>
                <tbody>
                    @forelse ($topGainer as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->previous, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row->live_price, 0, ',', '.') }}</td>
                            <td class="text-right text-green">+{{ number_format($row->diff, 0, ',', '.') }} / +{{ number_format($row->change_pct, 2) }}%</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="4">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Top Loser</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th class="text-right">Previous</th><th class="text-right">Live Price</th><th class="text-right">Change</th></tr></thead>
                <tbody>
                    @forelse ($topLoser as $row)
                        <tr>
                            <td><span class="code-pill">{{ $row->stock_code }}</span></td>
                            <td class="text-right">{{ number_format($row->previous, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($row->live_price, 0, ',', '.') }}</td>
                            <td class="text-right text-red">{{ number_format($row->diff, 0, ',', '.') }} / {{ number_format($row->change_pct, 2) }}%</td>
                        </tr>
                    @empty
                        <tr class="empty-row"><td colspan="4">Belum ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<p style="color:var(--muted); font-size:0.72rem; margin-top:1rem;">
    * Live Price dari Yahoo Finance (fallback ke Close terakhir kalau limit/timeout). Sinyal Acc/Dist dihitung dari data broker summary saham-saham paling aktif (by value transaksi) pada rentang tanggal dipilih. Hasil di-cache 3 jam untuk menghemat kuota API.
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
