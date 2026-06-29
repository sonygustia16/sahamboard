@extends('layouts.app')

@section('title', 'Analysis & Chart')
@section('page-title', 'Analysis & Chart — Screen 2')
@section('page-subtitle', 'Cari berdasarkan stock code tunggal dan pantau tren value transaksi')

@push('head-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Cari Stock Code</h3>
        <form action="{{ route('analysis.index') }}" method="GET">
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
                    <input type="text" name="stock_code" value="{{ $stockCode }}" placeholder="Contoh: BBCA, ACES" style="width:160px;">
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Cari Saham</button>
                <a href="{{ route('analysis.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
    </div>

    @if (!empty($chartValues))
    <div class="chart-container">
        <canvas id="valueLineChart"></canvas>
    </div>
    @endif

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
                        $formattedLive = "<span class='status-chip'>Offline/Error</span>";

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
                    <tr class="empty-row"><td colspan="8">Data tidak ditemukan atau silakan isi kriteria pencarian</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection

@push('body-scripts')
@if (!empty($chartValues))
<script>
    const ctx = document.getElementById('valueLineChart').getContext('2d');

    const gradient = ctx.createLinearGradient(0, 0, 0, 320);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.35)');
    gradient.addColorStop(0.6, 'rgba(59, 130, 246, 0.08)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.00)');

    Chart.defaults.color = '#94a3b8';
    Chart.defaults.font.family = "'Inter', sans-serif";

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                label: 'Transaksi Value',
                data: @json($chartValues),
                borderColor: '#3b82f6',
                borderWidth: 2.5,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#3b82f6',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
                fill: true,
                backgroundColor: gradient,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#64748b', maxRotation: 0 } },
                y: {
                    beginAtZero: false,
                    grid: { color: 'rgba(148,163,184,0.08)' },
                    ticks: {
                        color: '#64748b',
                        callback: function(value) { return new Intl.NumberFormat('id-ID').format(value); }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    borderColor: 'rgba(59,130,246,0.3)',
                    borderWidth: 1,
                    titleColor: '#fff',
                    bodyColor: '#e2e8f0',
                    padding: 10,
                    callbacks: {
                        label: function(context) {
                            return 'Value: Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                        }
                    }
                }
            }
        }
    });
</script>
@endif
@endpush
