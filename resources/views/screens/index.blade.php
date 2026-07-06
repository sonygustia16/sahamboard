@extends('layouts.app')

@section('page-title', 'Analysis Pasar Nego')
@section('page-subtitle', 'Analisis transaksi negosiasi saham secara real-time')

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Kriteria Pencarian</h3>
        <form action="{{ route('index') }}" method="GET" onsubmit="clearThousandSeparators()">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="form-group">
                    <label>Finish Date</label>
                    <input type="date" name="finish_date" value="{{ $finishDate }}">
                </div>
                <div class="form-group">
                    <label>Stock Code</label>
                    <input type="text" name="stock_code" value="{{ $stockCode }}" placeholder="Contoh: BBCA" style="width:110px;">
                </div>
                <div class="form-group">
                    <label>Previous Price</label>
                    <div class="input-group">
                        <select name="op_previous">
                            <option value="=" @selected($opPrevious == '=')>=</option>
                            <option value=">" @selected($opPrevious == '>')>&gt;</option>
                            <option value="<" @selected($opPrevious == '<')>&lt;</option>
                        </select>
                        <input type="text" class="rupiah-input" name="previous" value="{{ $filterPrevious }}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Frequency</label>
                    <div class="input-group">
                        <select name="op_frequency">
                            <option value="=" @selected($opFrequency == '=')>=</option>
                            <option value=">" @selected($opFrequency == '>')>&gt;</option>
                            <option value="<" @selected($opFrequency == '<')>&lt;</option>
                        </select>
                        <input type="text" class="rupiah-input" name="frequency" value="{{ $filterFrequency }}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Value</label>
                    <div class="input-group">
                        <select name="op_value">
                            <option value="=" @selected($opValue == '=')>=</option>
                            <option value=">" @selected($opValue == '>')>&gt;</option>
                            <option value="<" @selected($opValue == '<')>&lt;</option>
                        </select>
                        <input type="text" class="rupiah-input" name="value" value="{{ $filterValue }}">
                    </div>
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Cari / Filter</button>
                <a href="{{ route('index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    {{-- Info banner — sekarang menampilkan info pagination --}}
    <div class="info-banner">
        💡 Menampilkan
        <strong>{{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }}</strong>
        dari <strong>{{ number_format($rows->total()) }}</strong> data
        @if($rows->lastPage() > 1)
            &nbsp;·&nbsp; Halaman <strong>{{ $rows->currentPage() }}</strong> dari <strong>{{ $rows->lastPage() }}</strong>
        @endif
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Date</th>
                    <th>Stock Code</th>
                    <th>Previous</th>
                    <th>Live Price</th>
                    <th>Change</th>
                    <th>Frequency</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $code = $row->stock_code;
                        $livePrice = $livePriceCache[$code] ?? null;
                        $changeText = '-';
                        $changeClass = 'text-gray';
                        $formattedLive = "<span class='status-chip'>Timeout/Limit</span>";

                        if ($livePrice !== null && $row->previous > 0) {
                            $diff = $livePrice - $row->previous;
                            $formattedLive = number_format($livePrice, 0, ',', '.');

                            if ($diff > 0) {
                                $changeText = '+' . number_format($diff, 0, ',', '.');
                                $changeClass = 'text-green';
                            } elseif ($diff < 0) {
                                $changeText = number_format($diff, 0, ',', '.');
                                $changeClass = 'text-red';
                            } else {
                                $changeText = '0';
                                $changeClass = 'text-gray';
                            }
                        }
                        // Nomor urut tetap benar saat pindah halaman
                        $no = ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration;
                    @endphp
                    <tr>
                        <td class="text-center"><strong>{{ $no }}</strong></td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M y') }}</td>
                        <td><span class="code-pill">{{ $code }}</span></td>
                        <td class="text-right">{{ number_format($row->previous, 0, ',', '.') }}</td>
                        <td class="text-right live-cell">{!! $formattedLive !!}</td>
                        <td class="text-center {{ $changeClass }}">{{ $changeText }}</td>
                        <td class="text-right">{{ number_format($row->frequency, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($row->value, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8">Data tidak ditemukan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination — muncul hanya kalau lebih dari 1 halaman --}}
    @if ($rows->lastPage() > 1)
        <div class="pagination-wrap">
            {{-- Prev --}}
            @if ($rows->onFirstPage())
                <span class="page-btn disabled">‹ Prev</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}" class="page-btn">‹ Prev</a>
            @endif

            {{-- Nomor halaman dengan ellipsis --}}
            @php
                $start = max(1, $rows->currentPage() - 2);
                $end   = min($rows->lastPage(), $rows->currentPage() + 2);
            @endphp

            @if ($start > 1)
                <a href="{{ $rows->url(1) }}" class="page-btn">1</a>
                @if ($start > 2)
                    <span class="page-btn disabled">…</span>
                @endif
            @endif

            @for ($i = $start; $i <= $end; $i++)
                @if ($i == $rows->currentPage())
                    <span class="page-btn active">{{ $i }}</span>
                @else
                    <a href="{{ $rows->url($i) }}" class="page-btn">{{ $i }}</a>
                @endif
            @endfor

            @if ($end < $rows->lastPage())
                @if ($end < $rows->lastPage() - 1)
                    <span class="page-btn disabled">…</span>
                @endif
                <a href="{{ $rows->url($rows->lastPage()) }}" class="page-btn">{{ $rows->lastPage() }}</a>
            @endif

            {{-- Next --}}
            @if ($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" class="page-btn">Next ›</a>
            @else
                <span class="page-btn disabled">Next ›</span>
            @endif
        </div>
    @endif

@endsection

@push('body-scripts')
<script>
    const inputs = document.querySelectorAll('.rupiah-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, "");
            this.value = value !== "" ? new Intl.NumberFormat('id-ID').format(value) : "";
        });
    });
    function clearThousandSeparators() {
        inputs.forEach(input => { input.value = input.value.replace(/\./g, ""); });
    }
</script>
@endpush