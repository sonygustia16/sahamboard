@extends('layouts.app')

@section('title', 'Watchlist')
@section('page-title', 'Watchlist')
@section('page-subtitle', 'Pantau saham target dengan harga live dan jarak ke target')

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Tambah ke Watchlist</h3>
        <form action="{{ route('watchlist.store') }}" method="POST">
            @csrf
            <div class="filter-grid">
                <div class="form-group">
                    <label>Stock Code</label>
                    <input type="text" name="stock_code" placeholder="Contoh: BBCA" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Target Price</label>
                    <input type="number" step="0.01" name="target_price" placeholder="0" style="width:130px;" required>
                </div>
                <div class="form-group">
                    <label>Catatan</label>
                    <input type="text" name="note" placeholder="Breakout resistance..." style="width:240px;">
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Tambah</button>
                <a href="{{ route('watchlist.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Stock Code</th>
                    <th>Live Price</th>
                    <th>Target Price</th>
                    <th>Jarak ke Target</th>
                    <th>Catatan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($watchlistRows as $row)
                    @php
                        $code = $row->stock_code;
                        $livePrice = $livePriceCache[$code] ?? null;
                        if ($livePrice !== null) {
                            $formattedLive = number_format($livePrice, 0, ',', '.');
                            $gap = $row->target_price - $livePrice;
                            $gapClass = $gap <= 0 ? 'text-green' : 'text-gray';
                            $gapText = $gap <= 0 ? 'Tercapai' : number_format($gap, 0, ',', '.') . ' poin';
                        } else {
                            $formattedLive = "<span class='status-chip'>Timeout/Limit</span>";
                            $gapClass = 'text-gray';
                            $gapText = '-';
                        }
                    @endphp
                    <tr>
                        <td class="text-center"><strong>{{ $loop->iteration }}</strong></td>
                        <td><span class="code-pill">{{ $code }}</span></td>
                        <td class="text-right live-cell">{!! $formattedLive !!}</td>
                        <td class="text-right">{{ number_format($row->target_price, 0, ',', '.') }}</td>
                        <td class="text-right {{ $gapClass }}">{{ $gapText }}</td>
                        <td>{{ $row->note }}</td>
                        <td class="text-center">
                            <form action="{{ route('watchlist.destroy', $row) }}" method="POST" onsubmit="return confirm('Hapus dari watchlist?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost" style="padding:0.35rem 0.7rem; font-size:0.75rem;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="7">Watchlist masih kosong — tambahkan saham target di atas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection
