@extends('layouts.app')

@section('page-title', 'Screening')
@section('page-subtitle', 'Cari saham berpotensi akumulasi & pantau tren value transaksi')

@push('head-scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('content')

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Kriteria Pencarian</h3>

        {{-- Preset filter tersimpan — klik untuk langsung isi & jalankan filter --}}
        @if($savedFilters->count() > 0)
        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            @foreach($savedFilters as $preset)
                <div style="display:flex; align-items:center; gap:0.3rem; background:var(--panel-2); border:1px solid var(--border); border-radius:999px; padding:0.3rem 0.4rem 0.3rem 0.9rem;">
                    <button type="button" onclick="applyPreset({{ $preset->id }})" style="background:none; border:none; color:var(--cyan); font-size:0.8rem; font-weight:600; cursor:pointer; font-family:var(--body);">
                        {{ $preset->name }}
                    </button>
                    <form action="{{ route('filter-preset.destroy', $preset->id) }}" method="POST" onsubmit="return confirm('Hapus preset {{ $preset->name }}?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:none; border:none; color:var(--muted); font-size:0.75rem; cursor:pointer; padding:0.2rem 0.4rem;" title="Hapus preset">✕</button>
                    </form>
                </div>
            @endforeach
        </div>
        @endif

        <form action="{{ route('screening.index') }}" method="GET" onsubmit="clearThousandSeparators()" id="filterForm">
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

            {{-- Screening otomatis: cari saham dengan pola Close turun + Value NR naik --}}
            <div style="margin-top:1rem; padding:0.7rem 0.9rem; background:var(--panel-2); border:1px solid var(--border); border-radius:8px; display:flex; align-items:center; gap:0.6rem;">
                <input type="checkbox" name="screening" value="akumulasi" id="screeningCheckbox" {{ $screening === 'akumulasi' ? 'checked' : '' }} style="width:16px; height:16px; accent-color: var(--cyan); cursor:pointer;">
                <label for="screeningCheckbox" style="cursor:pointer; font-size:0.85rem; color:var(--ink); margin:0;">
                    🔍 <strong>Screening: Berpotensi Akumulasi</strong>
                    <span style="color:var(--muted); font-size:0.75rem; display:block;">Tampilkan saham yang Close-nya turun tapi Value NR naik dibanding hari transaksi sebelumnya</span>
                </label>
            </div>

            <div class="action-row">
                <button type="submit" class="btn btn-primary">Cari / Filter</button>
                <a href="{{ route('screening.index') }}" class="btn btn-ghost">Reset</a>
                <button type="button" class="btn btn-ghost" onclick="openSavePresetPrompt()">💾 Simpan sebagai Preset</button>
            </div>
        </form>
    </div>

    {{-- Form tersembunyi buat submit preset baru --}}
    <form action="{{ route('filter-preset.store') }}" method="POST" id="savePresetForm" style="display:none;">
        @csrf
        <input type="hidden" name="name" id="presetNameInput">
        <input type="hidden" name="op_previous" id="presetOpPrevious">
        <input type="hidden" name="previous" id="presetPrevious">
        <input type="hidden" name="op_frequency" id="presetOpFrequency">
        <input type="hidden" name="frequency" id="presetFrequency">
        <input type="hidden" name="op_value" id="presetOpValue">
        <input type="hidden" name="value" id="presetValue">
    </form>

    {{-- Chart card — muncul begitu klik kode saham di tabel --}}
    <div class="chart-container" id="chartCard" style="display:none;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.8rem;">
            <div>
                <span id="chartStockCode" style="font-family:var(--mono); font-weight:700; font-size:1.1rem; color:var(--ink);"></span>
                <span style="color:var(--muted); font-size:0.8rem; margin-left:0.5rem;">Tren Value Transaksi</span>
            </div>
            <div id="timeframeButtons" style="display:flex; gap:0.3rem;">
                <button type="button" class="btn btn-ghost tf-btn" data-tf="7d" style="padding:0.3rem 0.7rem; font-size:0.75rem;">7H</button>
                <button type="button" class="btn btn-ghost tf-btn active" data-tf="1m" style="padding:0.3rem 0.7rem; font-size:0.75rem;">1M</button>
                <button type="button" class="btn btn-ghost tf-btn" data-tf="3m" style="padding:0.3rem 0.7rem; font-size:0.75rem;">3M</button>
                <button type="button" class="btn btn-ghost tf-btn" data-tf="6m" style="padding:0.3rem 0.7rem; font-size:0.75rem;">6M</button>
                <button type="button" class="btn btn-ghost tf-btn" data-tf="1y" style="padding:0.3rem 0.7rem; font-size:0.75rem;">1Y</button>
            </div>
        </div>

        {{-- Badge sinyal otomatis: bandingkan tren Value NR vs Close Price --}}
        <div id="signalBadge" style="display:none; margin-bottom:0.8rem; padding:0.6rem 0.9rem; border-radius:8px; font-size:0.8rem; font-weight:600;"></div>

        <div style="position:relative; height:260px;">
            <canvas id="clickChart"></canvas>
        </div>
        <div id="chartLoading" style="display:none; text-align:center; color:var(--muted); font-size:0.8rem; padding:0.5rem;">Memuat data...</div>
        <div id="chartEmpty" style="display:none; text-align:center; color:var(--muted); font-size:0.8rem; padding:0.5rem;">Belum ada data historis untuk saham ini di rentang waktu tersebut.</div>

        {{-- ══════════════════════════════════════════════════════════
             Tab switcher: Indikator vs Broker Summary
             (menggantikan tumpukan RSI/Stoch/MACD/Broker yang panjang
             ke bawah — sekarang cuma 1 panel aktif dalam satu waktu)
             ══════════════════════════════════════════════════════════ --}}
        <div id="indicatorPanels" style="display:none; margin-top:1rem;">
            <div style="display:flex; gap:0.3rem; margin-bottom:0.8rem; border-bottom:1px solid var(--border);">
                <button type="button" class="btn btn-ghost panel-tab-btn active" data-panel="indicators" style="padding:0.5rem 1rem; font-size:0.78rem; border-radius:6px 6px 0 0; border-bottom:2px solid var(--cyan);">📈 Indikator</button>
                <button type="button" class="btn btn-ghost panel-tab-btn" data-panel="broker" style="padding:0.5rem 1rem; font-size:0.78rem; border-radius:6px 6px 0 0; border-bottom:2px solid transparent;">🧾 Broker Summary</button>
            </div>

            {{-- Panel: Indikator (RSI, Stoch RSI, MACD) --}}
            <div id="panelIndicators" class="tab-panel">
                <div style="margin-bottom:1rem;">
                    <div style="font-size:0.75rem; color:var(--muted); margin-bottom:0.3rem; font-family:var(--mono);">RSI 14</div>
                    <div style="position:relative; height:110px;">
                        <canvas id="rsiChart"></canvas>
                    </div>
                </div>
                <div style="margin-bottom:1rem;">
                    <div style="font-size:0.75rem; color:var(--muted); margin-bottom:0.3rem; font-family:var(--mono);">Stochastic RSI 14,14,3,3</div>
                    <div style="position:relative; height:110px;">
                        <canvas id="stochRsiChart"></canvas>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--muted); margin-bottom:0.3rem; font-family:var(--mono);">MACD 12,26,9</div>
                    <div style="position:relative; height:130px;">
                        <canvas id="macdChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Panel: Broker Summary --}}
            <div id="panelBroker" class="tab-panel" style="display:none;">

                {{-- Baris kontrol: rentang tanggal + limit + toggle gross/net --}}
                <div style="display:flex; flex-wrap:wrap; align-items:end; gap:0.6rem; margin-bottom:0.8rem; padding:0.6rem 0.8rem; background:var(--panel-2); border:1px solid var(--border); border-radius:8px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.65rem;">Start Date</label>
                        <input type="date" id="brokerStartDate" style="font-size:0.78rem; padding:0.3rem 0.5rem;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size:0.65rem;">End Date</label>
                        <input type="date" id="brokerEndDate" style="font-size:0.78rem; padding:0.3rem 0.5rem;">
                    </div>

                    {{-- Toggle Gross / Net --}}
                    <div style="display:flex; align-items:center; gap:0.4rem; margin-left:auto;">
                        <span id="brokerModeLabelGross" style="font-size:0.72rem; font-weight:700; color:var(--cyan);">Gross</span>
                        <label class="broker-switch">
                            <input type="checkbox" id="brokerNetToggle">
                            <span class="broker-switch-slider"></span>
                        </label>
                        <span id="brokerModeLabelNet" style="font-size:0.72rem; color:var(--muted);">Net</span>
                    </div>

                    <button type="button" class="btn btn-ghost" id="brokerAllDataToggle" style="padding:0.35rem 0.7rem; font-size:0.72rem;" data-active="0">All Data</button>
                    <button type="button" class="btn btn-primary" id="brokerApplyBtn" style="padding:0.35rem 0.9rem; font-size:0.72rem;">Terapkan</button>
                </div>

                <div id="brokerLoading" style="display:none; text-align:center; color:var(--muted); font-size:0.8rem; padding:0.5rem;">Memuat data broker...</div>
                <div id="brokerEmpty" style="display:none; text-align:center; color:var(--muted); font-size:0.8rem; padding:0.5rem;">Data broker tidak tersedia untuk saham/rentang ini.</div>

                <div id="brokerTableWrap" style="display:none; overflow-x:auto; max-height:380px; overflow-y:auto;">
                    <table style="width:100%; font-size:0.78rem; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:var(--panel); z-index:1;">
                            <tr style="color:var(--muted); text-align:right;">
                                <th style="text-align:center; padding:0.4rem 0.3rem;">#</th>
                                <th style="text-align:left; padding:0.4rem 0.3rem;" id="brokerColBuyLabel">Buy</th>
                                <th style="padding:0.4rem 0.3rem;">B.Val</th>
                                <th style="padding:0.4rem 0.3rem;">B.Lot</th>
                                <th style="padding:0.4rem 0.3rem;">B.Avg</th>
                                <th style="text-align:left; padding:0.4rem 0.3rem;" id="brokerColSellLabel">Sell</th>
                                <th style="padding:0.4rem 0.3rem;">S.Val</th>
                                <th style="padding:0.4rem 0.3rem;">S.Lot</th>
                                <th style="padding:0.4rem 0.3rem;">S.Avg</th>
                            </tr>
                        </thead>
                        <tbody id="brokerTableBody"></tbody>
                    </table>
                </div>
                <div id="brokerDateRange" style="margin-top:0.4rem; font-size:0.7rem; color:var(--muted); text-align:right;"></div>
            </div>
        </div>

        {{-- Panduan cara baca sinyal — selalu tampil sebagai referensi --}}
        <div style="margin-top:0.8rem; padding:0.7rem 0.9rem; background:var(--panel-2); border:1px solid var(--border); border-radius:8px; font-size:0.72rem; color:var(--muted); line-height:1.6;">
            <strong style="color:var(--ink);">Cara baca:</strong>
            <span style="color:#10b981;">🟢 Close turun, Value NR naik</span> → berpotensi akumulasi (bagus, banyak transaksi meski harga ditekan turun).
            <span style="color:#f59e0b;">🟡 Close naik, Value NR turun</span> → hati-hati (kenaikan harga tidak didukung transaksi besar, rawan tidak solid).
        </div>
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
                    <th style="width:36px;"></th>
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
                            $pct = ($diff / $row->previous) * 100;
                            $formattedLive = number_format($livePrice, 0, ',', '.');

                            if ($diff > 0) {
                                $changeText = '+' . number_format($diff, 0, ',', '.') . ' / +' . number_format($pct, 2) . '%';
                                $changeClass = 'text-green';
                            } elseif ($diff < 0) {
                                $changeText = number_format($diff, 0, ',', '.') . ' / ' . number_format($pct, 2) . '%';
                                $changeClass = 'text-red';
                            } else {
                                $changeText = '0 / 0%';
                                $changeClass = 'text-gray';
                            }
                        }
                        // Nomor urut tetap benar saat pindah halaman
                        $no = ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration;
                        $isWatchlisted = in_array($code, $watchlistedCodes ?? [], true);
                    @endphp
                    <tr class="clickable-row" data-code="{{ $code }}" onclick="selectStock('{{ $code }}')" style="cursor:pointer;">
                        <td class="text-center" onclick="event.stopPropagation();">
                            <button type="button"
                                    class="star-btn {{ $isWatchlisted ? 'star-active' : '' }}"
                                    id="star-{{ $code }}"
                                    onclick="toggleWatchlistStar('{{ $code }}', {{ $livePrice ?? 'null' }}, '{{ $row->date }}')"
                                    title="{{ $isWatchlisted ? 'Hapus dari Watchlist' : 'Tambah ke Watchlist' }}">
                                {{ $isWatchlisted ? '★' : '☆' }}
                            </button>
                        </td>
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
                    <tr class="empty-row"><td colspan="9">Data tidak ditemukan</td></tr>
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
<style>
    .tf-btn.active { background: var(--cyan); color: #0a0e1a; border-color: var(--cyan); }
    .star-btn {
        background: none; border: none; cursor: pointer; font-size: 1.2rem; line-height: 1;
        color: var(--muted); padding: 0.15rem; transition: transform 0.15s, color 0.15s;
    }
    .star-btn:hover { transform: scale(1.2); color: #fbbf24; }
    .star-btn.star-active { color: #fbbf24; }
    tr.active-row td {
        background: rgba(34,211,238,0.10) !important;
        border-top: 1px solid rgba(34,211,238,0.35) !important;
        border-bottom: 1px solid rgba(34,211,238,0.35) !important;
    }
    tr.active-row td:first-child { border-left: 1px solid rgba(34,211,238,0.35) !important; border-radius: 8px 0 0 8px; }
    tr.active-row td:last-child { border-right: 1px solid rgba(34,211,238,0.35) !important; border-radius: 0 8px 8px 0; }

    /* ══ Broker Summary switch (Gross/Net toggle) ══ */
    .broker-switch { position: relative; display: inline-block; width: 38px; height: 20px; }
    .broker-switch input { opacity: 0; width: 0; height: 0; }
    .broker-switch-slider {
        position: absolute; cursor: pointer; inset: 0;
        background: var(--panel-2); border: 1px solid var(--border);
        transition: 0.2s; border-radius: 20px;
    }
    .broker-switch-slider:before {
        position: absolute; content: ""; height: 14px; width: 14px;
        left: 2px; bottom: 2px; background: var(--muted);
        transition: 0.2s; border-radius: 50%;
    }
    .broker-switch input:checked + .broker-switch-slider { background: rgba(34,211,238,0.25); border-color: var(--cyan); }
    .broker-switch input:checked + .broker-switch-slider:before { transform: translateX(18px); background: var(--cyan); }
</style>
<script>
    // Data preset dari server (untuk applyPreset)
    const SAVED_FILTERS = {!! $savedFilters->map(fn($p) => [
        'id' => $p->id,
        'op_previous' => $p->op_previous,
        'previous' => $p->previous,
        'op_frequency' => $p->op_frequency,
        'frequency' => $p->frequency,
        'op_value' => $p->op_value,
        'value' => $p->value,
    ])->values()->toJson() !!};

    function applyPreset(id) {
        const preset = SAVED_FILTERS.find(p => p.id === id);
        if (!preset) return;

        document.querySelector('[name="op_previous"]').value = preset.op_previous || '=';
        document.querySelector('[name="previous"]').value = preset.previous ? new Intl.NumberFormat('id-ID').format(preset.previous) : '';
        document.querySelector('[name="op_frequency"]').value = preset.op_frequency || '=';
        document.querySelector('[name="frequency"]').value = preset.frequency ? new Intl.NumberFormat('id-ID').format(preset.frequency) : '';
        document.querySelector('[name="op_value"]').value = preset.op_value || '=';
        document.querySelector('[name="value"]').value = preset.value ? new Intl.NumberFormat('id-ID').format(preset.value) : '';

        document.getElementById('filterForm').requestSubmit();
    }

    function openSavePresetPrompt() {
        const name = prompt('Nama preset ini (contoh: "Saham likuid tinggi"):');
        if (!name || !name.trim()) return;

        document.getElementById('presetNameInput').value = name.trim();
        document.getElementById('presetOpPrevious').value = document.querySelector('[name="op_previous"]').value;
        document.getElementById('presetPrevious').value = document.querySelector('[name="previous"]').value;
        document.getElementById('presetOpFrequency').value = document.querySelector('[name="op_frequency"]').value;
        document.getElementById('presetFrequency').value = document.querySelector('[name="frequency"]').value;
        document.getElementById('presetOpValue').value = document.querySelector('[name="op_value"]').value;
        document.getElementById('presetValue').value = document.querySelector('[name="value"]').value;

        document.getElementById('savePresetForm').submit();
    }

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

    // ══ Klik-langsung-chart dengan timeframe selector ══
    let clickChartInstance = null;
    let rsiChartInstance = null;
    let stochRsiChartInstance = null;
    let macdChartInstance = null;

    /**
     * Render 3 panel indikator (RSI, Stochastic RSI, MACD Histogram) di bawah chart utama.
     * Warna sengaja pakai palet tema (cyan/ungu/amber), BUKAN hijau-merah standar TradingView —
     * tapi cara baca sinyalnya tetap sama: RSI >70 = overbought, <30 = oversold, dst.
     */
    function renderIndicators(labels, rsi, stochK, stochD, macdLine, macdSignal, macdHist) {
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', sans-serif";

        const commonXAxis = {
            grid: { display: false },
            ticks: { color: '#64748b', maxRotation: 0, autoSkip: true, maxTicksLimit: 8 }
        };
        const commonGrid = { color: 'rgba(148,163,184,0.08)' };

        // ── RSI ──
        if (rsiChartInstance) rsiChartInstance.destroy();
        rsiChartInstance = new Chart(document.getElementById('rsiChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'RSI 14',
                    data: rsi,
                    borderColor: '#22d3ee',
                    borderWidth: 1.8,
                    pointRadius: 0,
                    tension: 0.3,
                    spanGaps: true
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: commonXAxis,
                    y: { min: 0, max: 100, grid: commonGrid, ticks: { color: '#64748b', stepSize: 25 } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b', borderColor: 'rgba(34,211,238,0.3)', borderWidth: 1,
                        callbacks: { label: (ctx) => 'RSI: ' + (ctx.raw !== null ? ctx.raw.toFixed(1) : '-') }
                    }
                },
                elements: { line: { borderJoinStyle: 'round' } }
            },
            plugins: [{
                id: 'rsiRefLines',
                afterDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const drawLine = (val, color) => {
                        const y = scales.y.getPixelForValue(val);
                        ctx.save();
                        ctx.strokeStyle = color;
                        ctx.setLineDash([4, 4]);
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(chartArea.left, y);
                        ctx.lineTo(chartArea.right, y);
                        ctx.stroke();
                        ctx.restore();
                    };
                    drawLine(70, 'rgba(167,139,250,0.5)');
                    drawLine(30, 'rgba(167,139,250,0.5)');
                }
            }]
        });

        // ── Stochastic RSI ──
        if (stochRsiChartInstance) stochRsiChartInstance.destroy();
        stochRsiChartInstance = new Chart(document.getElementById('stochRsiChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: '%K', data: stochK, borderColor: '#22d3ee', borderWidth: 1.8, pointRadius: 0, tension: 0.3, spanGaps: true },
                    { label: '%D', data: stochD, borderColor: '#a78bfa', borderWidth: 1.8, pointRadius: 0, tension: 0.3, spanGaps: true, borderDash: [3, 2] }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: commonXAxis,
                    y: { min: 0, max: 100, grid: commonGrid, ticks: { color: '#64748b', stepSize: 25 } }
                },
                plugins: {
                    legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 10 } } },
                    tooltip: {
                        backgroundColor: '#1e293b', borderColor: 'rgba(34,211,238,0.3)', borderWidth: 1,
                        callbacks: { label: (ctx) => ctx.dataset.label + ': ' + (ctx.raw !== null ? ctx.raw.toFixed(1) : '-') }
                    }
                }
            },
            plugins: [{
                id: 'stochRefLines',
                afterDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    const drawLine = (val) => {
                        const y = scales.y.getPixelForValue(val);
                        ctx.save();
                        ctx.strokeStyle = 'rgba(148,163,184,0.3)';
                        ctx.setLineDash([4, 4]);
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(chartArea.left, y);
                        ctx.lineTo(chartArea.right, y);
                        ctx.stroke();
                        ctx.restore();
                    };
                    drawLine(80);
                    drawLine(20);
                }
            }]
        });

        // ── MACD (garis MACD, garis Signal, histogram) ──
        if (macdChartInstance) macdChartInstance.destroy();
        const histColors = macdHist.map(v => v === null ? 'transparent' : (v >= 0 ? 'rgba(34,211,238,0.55)' : 'rgba(167,139,250,0.55)'));
        macdChartInstance = new Chart(document.getElementById('macdChart').getContext('2d'), {
            data: {
                labels: labels,
                datasets: [
                    { type: 'bar', label: 'Histogram', data: macdHist, backgroundColor: histColors, borderWidth: 0, order: 2 },
                    { type: 'line', label: 'MACD', data: macdLine, borderColor: '#22d3ee', borderWidth: 1.6, pointRadius: 0, tension: 0.3, spanGaps: true, order: 0 },
                    { type: 'line', label: 'Signal', data: macdSignal, borderColor: '#f59e0b', borderWidth: 1.6, pointRadius: 0, tension: 0.3, spanGaps: true, order: 1 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: commonXAxis,
                    y: { grid: commonGrid, ticks: { color: '#64748b', callback: (v) => v.toFixed(0) } }
                },
                plugins: {
                    legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 10 } } },
                    tooltip: {
                        backgroundColor: '#1e293b', borderColor: 'rgba(34,211,238,0.3)', borderWidth: 1,
                        callbacks: { label: (ctx) => ctx.dataset.label + ': ' + (ctx.raw !== null ? ctx.raw.toFixed(2) : '-') }
                    }
                }
            }
        });
    }
    let activeStockCode = null;
    let activeTimeframe = '1m';

    // ══ Toggle cepat ke Watchlist lewat ikon bintang ══
    function toggleWatchlistStar(code, livePrice, dateStr) {
        fetch('{{ route("watchlist.quick-toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ stock_code: code, live_price: livePrice, date: dateStr })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            const btn = document.getElementById('star-' + code);
            if (!btn) return;

            if (data.added) {
                btn.classList.add('star-active');
                btn.textContent = '★';
                btn.title = 'Hapus dari Watchlist';
            } else {
                btn.classList.remove('star-active');
                btn.textContent = '☆';
                btn.title = 'Tambah ke Watchlist';
            }
        })
        .catch(() => alert('Gagal update watchlist. Coba lagi.'));
    }

    function selectStock(code) {
        activeStockCode = code;

        // highlight baris aktif
        document.querySelectorAll('.clickable-row').forEach(tr => tr.classList.remove('active-row'));
        document.querySelectorAll(`.clickable-row[data-code="${code}"]`).forEach(tr => tr.classList.add('active-row'));

        document.getElementById('chartCard').style.display = 'block';
        document.getElementById('chartStockCode').textContent = code;
        document.getElementById('chartCard').scrollIntoView({ behavior: 'smooth', block: 'start' });

        loadChartData(code, activeTimeframe);
    }

    function loadChartData(code, timeframe) {
        const loadingEl = document.getElementById('chartLoading');
        const emptyEl = document.getElementById('chartEmpty');
        const canvas = document.getElementById('clickChart');

        loadingEl.style.display = 'block';
        emptyEl.style.display = 'none';
        canvas.style.display = 'block';

        fetch(`/chart-data/${code}?timeframe=${timeframe}`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                loadingEl.style.display = 'none';

                if (!data.values || data.values.length === 0) {
                    canvas.style.display = 'none';
                    emptyEl.style.display = 'block';
                    document.getElementById('signalBadge').style.display = 'none';
                    document.getElementById('indicatorPanels').style.display = 'none';
                    return;
                }

                renderClickChart(data.labels, data.values, data.closes);
                updateSignalBadge(data.values, data.closes);
                document.getElementById('indicatorPanels').style.display = 'block';
                renderIndicators(data.labels, data.rsi, data.stoch_k, data.stoch_d, data.macd_line, data.macd_signal, data.macd_hist);

                // Broker Summary di-load kalau tab-nya sedang aktif, atau di-reset flag-nya
                // supaya ke-load ulang ketika user pindah ke tab Broker (kode saham beda).
                delete brokerLoadedForCode[code];
                if (document.getElementById('panelBroker').style.display !== 'none') {
                    loadBrokerSummary(code);
                }
            })
            .catch(() => {
                loadingEl.style.display = 'none';
                emptyEl.textContent = 'Gagal memuat data chart. Coba lagi.';
                emptyEl.style.display = 'block';
                canvas.style.display = 'none';
            });
    }

    // Ambang batas minimum supaya cuma nangkep SPIKE beneran, bukan selisih tipis/noise.
    // Value NR wajar naik-turun drastis (bisa berkali lipat), makanya thresholdnya lebih besar dari Close.
    const SIGNAL_VALUE_PCT_THRESHOLD = 50; // Value NR harus berubah minimal 50%
    const SIGNAL_CLOSE_PCT_THRESHOLD = 1;  // Close harus berubah minimal 1%

    function pctChange(from, to) {
        if (!from || from === 0) return 0;
        return ((to - from) / Math.abs(from)) * 100;
    }

    // Bandingkan arah tren Value NR vs Close Price, lalu tampilkan sinyal.
    // Pakai rata-rata 20% data awal vs 20% data akhir supaya tidak terpengaruh 1 lonjakan tunggal (noise),
    // dan cuma dianggap sinyal valid kalau persentase perubahannya melewati threshold di atas.
    function trendPercent(arr) {
        const clean = arr.filter(v => v !== null && !isNaN(v));
        if (clean.length < 2) return 0;
        const n = clean.length;
        const chunk = Math.max(1, Math.floor(n * 0.2));
        const head = clean.slice(0, chunk).reduce((a, b) => a + b, 0) / chunk;
        const tail = clean.slice(-chunk).reduce((a, b) => a + b, 0) / chunk;
        return pctChange(head, tail);
    }

    function updateSignalBadge(values, closes) {
        const badge = document.getElementById('signalBadge');
        const valuePct = trendPercent(values);
        const closePct = trendPercent(closes);

        const valueSpikeUp = valuePct >= SIGNAL_VALUE_PCT_THRESHOLD;
        const valueSpikeDown = valuePct <= -SIGNAL_VALUE_PCT_THRESHOLD;
        const closeMoveUp = closePct >= SIGNAL_CLOSE_PCT_THRESHOLD;
        const closeMoveDown = closePct <= -SIGNAL_CLOSE_PCT_THRESHOLD;

        if (closeMoveDown && valueSpikeUp) {
            badge.style.display = 'block';
            badge.style.background = 'rgba(16,185,129,0.12)';
            badge.style.border = '1px solid rgba(16,185,129,0.4)';
            badge.style.color = '#10b981';
            badge.innerHTML = `🟢 Berpotensi Akumulasi — Close turun ${closePct.toFixed(1)}%, Value NR naik ${valuePct.toFixed(0)}%. Transaksi melonjak signifikan walau harga tertekan, bisa jadi tanda bandar sedang mengumpulkan.`;
        } else if (closeMoveUp && valueSpikeDown) {
            badge.style.display = 'block';
            badge.style.background = 'rgba(245,158,11,0.12)';
            badge.style.border = '1px solid rgba(245,158,11,0.4)';
            badge.style.color = '#f59e0b';
            badge.innerHTML = `🟡 Hati-hati — Close naik ${closePct.toFixed(1)}%, Value NR turun ${Math.abs(valuePct).toFixed(0)}%. Kenaikan harga tidak didukung transaksi besar, waspada potensi tidak solid.`;
        } else {
            badge.style.display = 'block';
            badge.style.background = 'rgba(148,163,184,0.10)';
            badge.style.border = '1px solid var(--border)';
            badge.style.color = 'var(--muted)';
            badge.innerHTML = '⚪ Tidak ada sinyal divergensi signifikan pada rentang waktu ini.';
        }
    }

    // Format angka besar jadi singkat: 1.250.000.000 -> 1,25 M | 850.000.000 -> 850 Jt | dst.
    function formatSingkat(num) {
        const abs = Math.abs(num);
        if (abs >= 1e12) return (num / 1e12).toFixed(2).replace('.', ',') + ' T';
        if (abs >= 1e9)  return (num / 1e9).toFixed(2).replace('.', ',') + ' M';
        if (abs >= 1e6)  return (num / 1e6).toFixed(2).replace('.', ',') + ' Jt';
        if (abs >= 1e3)  return (num / 1e3).toFixed(0) + ' Rb';
        return new Intl.NumberFormat('id-ID').format(num);
    }

    let currentChartValues = [];
    let currentChartCloses = [];

    function renderClickChart(labels, values, closes) {
        currentChartValues = values;
        currentChartCloses = closes;
        const ctx = document.getElementById('clickChart').getContext('2d');

        if (clickChartInstance) {
            clickChartInstance.destroy();
        }

        const gradient = ctx.createLinearGradient(0, 0, 0, 260);
        gradient.addColorStop(0, 'rgba(34, 211, 238, 0.30)');
        gradient.addColorStop(0.6, 'rgba(34, 211, 238, 0.06)');
        gradient.addColorStop(1, 'rgba(34, 211, 238, 0.00)');

        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Inter', sans-serif";

        clickChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Value NR',
                        data: values,
                        borderColor: '#22d3ee',
                        borderWidth: 2.5,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#22d3ee',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        fill: true,
                        backgroundColor: gradient,
                        tension: 0.4,
                        yAxisID: 'yValue'
                    },
                    {
                        label: 'Close Price',
                        data: closes,
                        borderColor: '#a78bfa',
                        borderWidth: 2,
                        borderDash: [4, 3],
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#a78bfa',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'yClose'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b', maxRotation: 0 } },
                    yValue: {
                        position: 'left',
                        beginAtZero: false,
                        grid: { color: 'rgba(148,163,184,0.08)' },
                        ticks: {
                            color: '#22d3ee',
                            callback: function(value) { return formatSingkat(value); }
                        },
                        title: { display: true, text: 'Value NR', color: '#22d3ee', font: { size: 10 } }
                    },
                    yClose: {
                        position: 'right',
                        beginAtZero: false,
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#a78bfa',
                            callback: function(value) { return new Intl.NumberFormat('id-ID').format(value); }
                        },
                        title: { display: true, text: 'Close Price', color: '#a78bfa', font: { size: 10 } }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: { color: '#94a3b8', boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        borderColor: 'rgba(34,211,238,0.3)',
                        borderWidth: 1,
                        titleColor: '#fff',
                        bodyColor: '#e2e8f0',
                        footerColor: function(items) {
                            if (!items || !items.length) return '#94a3b8';
                            const idx = items[0].dataIndex;
                            if (idx === 0) return '#94a3b8';

                            const valPct = pctChange(currentChartValues[idx - 1], currentChartValues[idx]);
                            const closePct = pctChange(currentChartCloses[idx - 1], currentChartCloses[idx]);

                            if (closePct <= -SIGNAL_CLOSE_PCT_THRESHOLD && valPct >= SIGNAL_VALUE_PCT_THRESHOLD) {
                                return '#10b981'; // hijau, samain sama badge akumulasi
                            } else if (closePct >= SIGNAL_CLOSE_PCT_THRESHOLD && valPct <= -SIGNAL_VALUE_PCT_THRESHOLD) {
                                return '#f59e0b'; // kuning/amber, samain sama badge hati-hati
                            }
                            return '#94a3b8';
                        },
                        footerFont: { weight: '600', size: 11 },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                if (context.dataset.yAxisID === 'yClose') {
                                    return 'Close: Rp ' + new Intl.NumberFormat('id-ID').format(context.raw);
                                }
                                return 'Value NR: Rp ' + formatSingkat(context.raw);
                            },
                            footer: function(items) {
                                const idx = items[0].dataIndex;
                                if (idx === 0) return '';

                                const valPct = pctChange(currentChartValues[idx - 1], currentChartValues[idx]);
                                const closePct = pctChange(currentChartCloses[idx - 1], currentChartCloses[idx]);

                                if (closePct <= -SIGNAL_CLOSE_PCT_THRESHOLD && valPct >= SIGNAL_VALUE_PCT_THRESHOLD) {
                                    return `🟢 Close turun ${closePct.toFixed(1)}%, Value naik ${valPct.toFixed(0)}% — berpotensi akumulasi`;
                                } else if (closePct >= SIGNAL_CLOSE_PCT_THRESHOLD && valPct <= -SIGNAL_VALUE_PCT_THRESHOLD) {
                                    return `🟡 Close naik ${closePct.toFixed(1)}%, Value turun ${Math.abs(valPct).toFixed(0)}% — hati-hati`;
                                }
                                return '';
                            }
                        }
                    }
                }
            }
        });
    }

    document.querySelectorAll('.tf-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.tf-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            activeTimeframe = this.dataset.tf;
            if (activeStockCode) {
                loadChartData(activeStockCode, activeTimeframe);
            }
        });
    });

    // ══════════════════════════════════════════════════════════
    // Tab switcher: Indikator vs Broker Summary
    // ══════════════════════════════════════════════════════════
    document.querySelectorAll('.panel-tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.panel-tab-btn').forEach(b => {
                b.classList.remove('active');
                b.style.borderBottom = '2px solid transparent';
            });
            this.classList.add('active');
            this.style.borderBottom = '2px solid var(--cyan)';

            const target = this.dataset.panel;
            document.getElementById('panelIndicators').style.display = target === 'indicators' ? 'block' : 'none';
            document.getElementById('panelBroker').style.display = target === 'broker' ? 'block' : 'none';

            // Broker data baru di-load pertama kali tab-nya dibuka (hemat request)
            if (target === 'broker' && activeStockCode && !brokerLoadedForCode[activeStockCode]) {
                loadBrokerSummary(activeStockCode);
            }
        });
    });

    // ══════════════════════════════════════════════════════════
    // Broker Summary panel — konsumsi API cuan.jumari.app/api/broker-summary/{code}
    // ══════════════════════════════════════════════════════════
    let brokerAllDataActive = false;
    const brokerLoadedForCode = {}; // cache flag: sudah pernah di-load utk kode ini di sesi ini

    function formatSingkatBroker(num) {
        const abs = Math.abs(num || 0);
        const sign = num < 0 ? '-' : '';
        if (abs >= 1e12) return sign + (abs / 1e12).toFixed(2).replace('.', ',') + ' T';
        if (abs >= 1e9)  return sign + (abs / 1e9).toFixed(2).replace('.', ',') + ' M';
        if (abs >= 1e6)  return sign + (abs / 1e6).toFixed(2).replace('.', ',') + ' Jt';
        if (abs >= 1e3)  return sign + (abs / 1e3).toFixed(0) + ' Rb';
        return new Intl.NumberFormat('id-ID').format(num || 0);
    }

    function renderBrokerTable(levels, net) {
        const tbody = document.getElementById('brokerTableBody');
        tbody.innerHTML = '';

        levels.forEach((lvl, idx) => {
            const buy = lvl.buy;
            const sell = lvl.sell;

            // Gross mode: field standar bval/bvol/sval/svol, avg dihitung manual.
            // Net mode: field sama namanya tapi isinya net value/volume, avg pakai bavg/savg dari API.
            const buyCode = buy ? buy.broker_code : '-';
            const buyVal = buy ? formatSingkatBroker(buy.bval) : '-';
            const buyLot = buy ? formatSingkatBroker(buy.bvol) : '-';
            const buyAvg = buy
                ? (net
                    ? (buy.bavg !== null && buy.bavg !== undefined ? buy.bavg.toFixed(2) : '-')
                    : (buy.bvol ? (buy.bval / buy.bvol).toFixed(2) : '-'))
                : '-';

            const sellCode = sell ? sell.broker_code : '-';
            const sellVal = sell ? formatSingkatBroker(sell.sval) : '-';
            const sellLot = sell ? formatSingkatBroker(sell.svol) : '-';
            const sellAvg = sell
                ? (net
                    ? (sell.savg !== null && sell.savg !== undefined ? sell.savg.toFixed(2) : '-')
                    : (sell.svol ? (sell.sval / sell.svol).toFixed(2) : '-'))
                : '-';

            const tr = document.createElement('tr');
            tr.style.borderTop = '1px solid var(--border)';
            tr.innerHTML = `
                <td style="text-align:center; padding:0.4rem 0.3rem; color:var(--muted);">${idx + 1}</td>
                <td style="text-align:left; padding:0.4rem 0.3rem;"><span class="code-pill">${buyCode}</span></td>
                <td style="text-align:right; padding:0.4rem 0.3rem; color:#10b981;">${buyVal}</td>
                <td style="text-align:right; padding:0.4rem 0.3rem;">${buyLot}</td>
                <td style="text-align:right; padding:0.4rem 0.3rem;">${buyAvg}</td>
                <td style="text-align:left; padding:0.4rem 0.3rem;"><span class="code-pill">${sellCode}</span></td>
                <td style="text-align:right; padding:0.4rem 0.3rem; color:#f43f5e;">${sellVal}</td>
                <td style="text-align:right; padding:0.4rem 0.3rem;">${sellLot}</td>
                <td style="text-align:right; padding:0.4rem 0.3rem;">${sellAvg}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function updateBrokerModeLabels(net) {
        const gross = document.getElementById('brokerModeLabelGross');
        const netLbl = document.getElementById('brokerModeLabelNet');
        gross.style.color = net ? 'var(--muted)' : 'var(--cyan)';
        gross.style.fontWeight = net ? '400' : '700';
        netLbl.style.color = net ? 'var(--cyan)' : 'var(--muted)';
        netLbl.style.fontWeight = net ? '700' : '400';

        // Kolom Buy = top net buyer, Sell = top net seller kalau mode net aktif
        document.getElementById('brokerColBuyLabel').textContent = net ? 'Buy (Net+)' : 'Buy';
        document.getElementById('brokerColSellLabel').textContent = net ? 'Sell (Net-)' : 'Sell';
    }

    function loadBrokerSummary(code) {
        if (!code) return;

        const loadingEl = document.getElementById('brokerLoading');
        const emptyEl = document.getElementById('brokerEmpty');
        const wrapEl = document.getElementById('brokerTableWrap');

        loadingEl.style.display = 'block';
        emptyEl.style.display = 'none';
        wrapEl.style.display = 'none';

        const net = document.getElementById('brokerNetToggle').checked;
        const startDate = document.getElementById('brokerStartDate').value;
        const endDate = document.getElementById('brokerEndDate').value;

        updateBrokerModeLabels(net);

        const params = new URLSearchParams();
        if (startDate) params.set('start_date', startDate);
        if (endDate) params.set('end_date', endDate);
        if (net) params.set('net', '1');

        if (brokerAllDataActive) {
            params.set('all_data', '1');
        } else {
            params.set('broker_limit', '10');
            params.set('level_limit', '8');
        }

        fetch(`/broker-summary/${code}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
            .then(res => {
                if (!res.ok) throw new Error('not ok');
                return res.json();
            })
            .then(data => {
                loadingEl.style.display = 'none';
                brokerLoadedForCode[code] = true;

                if (!data.broker_levels || data.broker_levels.length === 0) {
                    emptyEl.style.display = 'block';
                    return;
                }

                // Isi input tanggal dengan rentang yang benar-benar dipakai backend
                // (berguna kalau user belum isi tanggal sama sekali -> otomatis terisi default API)
                if (!startDate && data.broker_start_date) {
                    document.getElementById('brokerStartDate').value = data.broker_start_date;
                }
                if (!endDate && data.broker_end_date) {
                    document.getElementById('brokerEndDate').value = data.broker_end_date;
                }

                renderBrokerTable(data.broker_levels, net);
                document.getElementById('brokerDateRange').textContent =
                    `Periode: ${data.broker_start_date} s/d ${data.broker_end_date}` +
                    (data.broker_date_min ? ` · Data tersedia dari ${data.broker_date_min} s/d ${data.broker_date_max}` : '');
                wrapEl.style.display = 'block';
            })
            .catch(() => {
                loadingEl.style.display = 'none';
                emptyEl.textContent = 'Gagal memuat data broker. Coba lagi.';
                emptyEl.style.display = 'block';
            });
    }

    document.getElementById('brokerApplyBtn').addEventListener('click', function () {
        if (activeStockCode) loadBrokerSummary(activeStockCode);
    });

    document.getElementById('brokerNetToggle').addEventListener('change', function () {
        if (activeStockCode) loadBrokerSummary(activeStockCode);
    });

    document.getElementById('brokerAllDataToggle').addEventListener('click', function () {
        brokerAllDataActive = !brokerAllDataActive;
        this.dataset.active = brokerAllDataActive ? '1' : '0';
        this.style.background = brokerAllDataActive ? 'var(--cyan)' : '';
        this.style.color = brokerAllDataActive ? '#0a0e1a' : '';
        this.style.borderColor = brokerAllDataActive ? 'var(--cyan)' : '';
        if (activeStockCode) loadBrokerSummary(activeStockCode);
    });
</script>
@endpush
