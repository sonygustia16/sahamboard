@extends('layouts.app')

@section('title', 'Filter Lengkap')
@section('page-title', 'Filter Data Saham — Screen 1')
@section('page-subtitle', 'Pencarian multi-kriteria lengkap dengan harga live Yahoo Finance')

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

    <div class="info-banner">
        💡 Menampilkan <strong>{{ $rows->count() }}</strong> data terbaru untuk mencegah sistem lambat saat memuat harga Live Yahoo Finance.
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
                    @endphp
                    <tr>
                        <td class="text-center"><strong>{{ $loop->iteration }}</strong></td>
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
