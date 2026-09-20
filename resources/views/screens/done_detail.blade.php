@extends('layouts.app')

@section('page-title', 'Screening · Done Detail')
@section('page-subtitle', '10 besar HAKA dominan dengan riwayat kemunculan')

@section('content')

    {{-- Navigasi antar mode screening --}}
    <div style="display:flex; gap:0.5rem; margin-bottom:1rem; flex-wrap:wrap;">
        <a href="{{ route('screening.index') }}" class="btn btn-ghost">Screening Value NR</a>
        <a href="{{ route('done-detail.index') }}" class="btn btn-primary">Done Detail</a>
    </div>

    <div class="glass-card">
        <h3><span class="accent-bar"></span>Kriteria Done Detail</h3>
        <form action="{{ route('done-detail.index') }}" method="GET" onsubmit="clearJumboSeparators()">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Start Date (awal riwayat)</label>
                    <input type="date" name="start_date" value="{{ $startDate }}">
                </div>
                <div class="form-group">
                    <label>Finish Date (hari dilihat)</label>
                    <input type="date" name="finish_date" value="{{ $finishDate }}">
                </div>
                <div class="form-group">
                    <label>Rasio HAKA/HAKI min.</label>
                    <input type="number" name="min_ratio" step="0.5" min="1" value="{{ $minRatio }}" style="width:90px;">
                </div>
                <div class="form-group">
                    <label>Total value min. (Rp M)</label>
                    <input type="number" name="min_total_m" step="0.5" min="0" value="{{ $minTotalM }}" style="width:90px;">
                </div>
                <div class="form-group">
                    <label>HAKA jumbo min. per menit (Rp)</label>
                    <input type="text" name="jumbo_min" id="jumboInput" value="{{ number_format($jumbo, 0, ',', '.') }}" inputmode="numeric" style="width:170px;">
                </div>
            </div>
            <div class="action-row">
                <button type="submit" class="btn btn-primary">Analisis</button>
                <a href="{{ route('done-detail.index') }}" class="btn btn-ghost">Reset</a>
            </div>
        </form>
        <div style="margin-top:0.8rem; font-size:0.72rem; color:var(--muted); line-height:1.6;">
            Maksimal {{ $maxDays }} hari bursa per pencarian. 10 besar dihitung untuk Finish Date; hari-hari sebelumnya
            dipakai sebagai riwayat. Emiten lolos jika HAKA lebih besar dari HAKI dan rasionya di atas batas.
            Semua emiten likuid dianalisis (rata-rata value ≥ Rp 5 M dan frekuensi ≥ 500x per hari, maksimal {{ $maxCandidates }} emiten), lalu ditampilkan 10 terbaik.
            Hanya papan RG; transaksi crossing satu broker tidak dihitung.
            @if($jumboClamped)
                <br><span style="color:#f59e0b;">Batas jumbo minimal Rp {{ number_format($jumboFloor, 0, ',', '.') }}, jadi angka itu yang dipakai.</span>
            @endif
        </div>
    </div>

    <div class="info-banner" id="statusBanner">
        @if(empty($dates))
            💡 Tidak ada data bursa pada rentang tanggal ini.
        @elseif(empty($candidates))
            💡 Tidak ada emiten yang memenuhi syarat likuiditas pada rentang ini.
        @else
            💡 Menyiapkan analisis {{ count($candidates) }} emiten untuk {{ count($dates) }} hari bursa…
        @endif
    </div>

    <div id="progressWrap" style="display:none; margin-bottom:1rem;">
        <div style="height:6px; background:var(--panel-2); border-radius:3px; overflow:hidden;">
            <div id="progressBar" style="height:100%; width:0%; background:var(--cyan); transition:width .2s;"></div>
        </div>
    </div>

    <div id="brokerStrip" class="dd-strip" style="display:none;"></div>

    <div id="cardsGrid" class="dd-grid"></div>
    <div id="cardsEmpty" style="display:none; text-align:center; color:var(--muted); font-size:0.85rem; padding:1.5rem;">
        Belum ada emiten yang lolos untuk hari yang dilihat.
    </div>

@endsection

@push('body-scripts')
<style>
    .dd-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:0.8rem; align-items:start; }
    .dd-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:0.9rem 1rem; }
    .dd-row { display:flex; justify-content:space-between; align-items:center; }
    .dd-rank { width:26px; height:26px; border-radius:50%; background:var(--panel-2); display:flex; align-items:center; justify-content:center; font-size:0.75rem; font-weight:700; }
    .dd-code { font-family:var(--mono); font-weight:700; font-size:1.05rem; color:var(--ink); }
    .dd-pill { font-family:var(--mono); font-size:0.8rem; font-weight:700; padding:0.15rem 0.6rem; border-radius:999px; background:rgba(16,185,129,0.15); color:#10b981; }
    .dd-tag { font-size:0.68rem; font-weight:600; padding:0.1rem 0.5rem; border-radius:999px; }
    .dd-tag.new { background:rgba(245,158,11,0.18); color:#f59e0b; }
    .dd-tag.mid { background:rgba(34,211,238,0.15); color:#22d3ee; }
    .dd-tag.old { background:rgba(148,163,184,0.18); color:#cbd5e1; }
    .dd-tag.wait { background:rgba(148,163,184,0.12); color:var(--muted); }
    .dd-warn { font-size:0.65rem; color:#f59e0b; }
    .dd-dots { display:flex; justify-content:space-between; margin-top:0.7rem; }
    .dd-dot { display:flex; flex-direction:column; align-items:center; gap:3px; font-family:var(--mono); font-size:0.62rem; color:var(--muted); }
    .dd-dot i { width:14px; height:14px; border-radius:50%; background:var(--bg); border:1px solid var(--border); display:block; }
    .dd-dot i.on { background:#10b981; border-color:#10b981; }
    .dd-bar { display:flex; height:8px; border-radius:4px; overflow:hidden; margin:0.8rem 0 0.35rem; }
    .dd-bar .g { background:#10b981; }
    .dd-bar .r { background:var(--loss, #f43f5e); }
    .dd-mini-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.5rem; margin-top:0.6rem; }
    .dd-mini { background:var(--panel-2); border-radius:8px; padding:0.5rem 0.6rem; }
    .dd-mini .l { font-size:0.68rem; color:var(--muted); }
    .dd-mini .v { font-family:var(--mono); font-size:0.85rem; font-weight:700; color:var(--ink); }
    .dd-note { margin-top:0.6rem; font-size:0.75rem; color:var(--muted); }
    .dd-note strong { color:var(--ink); }

    .dd-drop { margin-top:0.8rem; border:1px solid var(--border); border-radius:10px; overflow:hidden; }
    .dd-drop > summary { list-style:none; cursor:pointer; padding:0.6rem 0.8rem; background:var(--bg); display:flex; justify-content:space-between; align-items:center; font-size:0.82rem; font-weight:600; color:var(--ink); }
    .dd-drop > summary::-webkit-details-marker { display:none; }
    .dd-drop > summary .chev { transition:transform .15s; color:var(--muted); }
    .dd-drop[open] > summary .chev { transform:rotate(180deg); }
    .dd-drop-tools { display:flex; align-items:center; gap:0.5rem; padding:0.5rem 0.8rem; font-size:0.72rem; color:var(--muted); border-bottom:1px solid var(--border); }
    .dd-drop-tools select { width:auto; padding:0.15rem 0.4rem; font-size:0.72rem; }
    .dd-scroll { max-height:260px; overflow-y:auto; }
    .dd-tbl { width:100%; border-collapse:collapse; font-size:0.72rem; }
    .dd-tbl th { position:sticky; top:0; background:var(--panel); font-family:var(--mono); font-weight:600; color:var(--muted); text-align:right; padding:0.45rem 0.4rem; border-bottom:1px solid var(--border); }
    .dd-tbl td { padding:0.45rem 0.4rem; text-align:right; border-top:1px solid var(--border); vertical-align:top; }
    .dd-tbl th:first-child, .dd-tbl td:first-child { text-align:left; padding-left:0.8rem; }
    .dd-tbl th:last-child, .dd-tbl td:last-child { padding-right:0.8rem; }
    .dd-tbl .t { font-family:var(--mono); font-weight:700; color:var(--ink); }
    .dd-tbl .v { font-family:var(--mono); font-weight:700; color:#10b981; }
    .dd-b { display:block; color:#10b981; white-space:nowrap; }
    .dd-b b { font-family:var(--mono); font-weight:700; }
    .dd-empty-row td { text-align:center !important; color:var(--muted); padding:0.8rem !important; }
    .dd-foot { padding:0.4rem 0.8rem; text-align:center; font-size:0.68rem; color:var(--muted); background:var(--bg); border-top:1px solid var(--border); }

    .dd-strip { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:0.7rem 0.9rem; margin-bottom:0.8rem; }
    .dd-strip .ttl { font-size:0.72rem; color:var(--muted); margin-bottom:0.4rem; }
    .dd-chip { display:inline-block; font-size:0.75rem; padding:0.15rem 0.6rem; border-radius:6px; background:rgba(16,185,129,0.15); color:#10b981; margin:0 0.4rem 0.3rem 0; }
    .dd-chip b { font-family:var(--mono); }
</style>
<script>
    @php
        // dates: urut terbaru dulu, dates[0] = hari yang dilihat
        $cfg = [
            'candidates' => $candidates,
            'dates'      => $dates,
            'minRatio'   => $minRatio,
            'minTotal'   => $minTotalM * 1e9,
            'jumbo'      => $jumbo,
            'url'        => route('done-detail.analyze', ['stockCode' => '__CODE__', 'date' => '__DATE__']),
        ];
    @endphp
    const CFG = @json($cfg);

    // store[code][date] = ringkasan hari itu dari server
    const store = {};
    const openCodes = new Set();
    const sortMode = {};        // code -> 'value' | 'lot' | 'time'
    let userToggled = false;
    let doneCount = 0, failedCount = 0;
    const totalTasks = CFG.candidates.length * CFG.dates.length;

    const nf = new Intl.NumberFormat('id-ID');
    function fmtSingkat(num) {
        const abs = Math.abs(num);
        if (abs >= 1e12) return (num / 1e12).toFixed(2).replace('.', ',') + ' T';
        if (abs >= 1e9)  return (num / 1e9).toFixed(2).replace('.', ',') + ' M';
        if (abs >= 1e6)  return (num / 1e6).toFixed(1).replace('.', ',') + ' Jt';
        return nf.format(Math.round(num));
    }
    function fmtRatio(r) { return r >= 999 ? '∞' : r.toFixed(1).replace('.', ',') + 'x'; }
    function dm(d) { return d.slice(8, 10) + '/' + d.slice(5, 7); }
    function esc(s) { return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

    function ratioOf(d) { return d.haki_value > 0 ? d.haka_value / d.haki_value : 999; }
    function hakiJumbo(d) { return (d.haki_minutes || []).filter(m => m.value >= CFG.jumbo).length; }

    // Daftar 10 besar untuk satu tanggal. Syarat: HAKA > HAKI, rasio & total value di atas batas.
    // Urutan: yang bersih dari HAKI jumbo dulu, lalu rasio tertinggi.
    function top10For(date) {
        const arr = [];
        Object.keys(store).forEach(code => {
            const d = store[code][date];
            if (!d) return;
            if (!(d.haka_value > d.haki_value)) return;
            const ratio = ratioOf(d);
            if (ratio < CFG.minRatio) return;
            if (d.haka_value + d.haki_value < CFG.minTotal) return;
            arr.push({ code, d, ratio, hj: hakiJumbo(d) });
        });
        arr.sort((a, b) =>
            ((a.hj > 0) - (b.hj > 0)) || (b.ratio - a.ratio) || (b.d.haka_value - a.d.haka_value));
        return arr.slice(0, 10);
    }

    function computeRanks() {
        const ranks = {};
        CFG.dates.forEach(date => {
            ranks[date] = {};
            top10For(date).forEach((it, i) => { ranks[date][it.code] = i + 1; });
        });
        return ranks;
    }

    function eventsFor(code) {
        const list = [];
        CFG.dates.forEach(date => {
            const d = store[code][date];
            if (!d) return;
            (d.haka_minutes || []).forEach(m => {
                if (m.value >= CFG.jumbo) list.push({ date, ...m });
            });
        });
        const mode = sortMode[code] || 'value';
        list.sort((a, b) => {
            if (mode === 'lot') return b.lot - a.lot;
            if (mode === 'time') return (b.date + b.time).localeCompare(a.date + a.time);
            return b.value - a.value;
        });
        return list;
    }

    function tagFor(code, ranks, ready) {
        if (!ready) return '<span class="dd-tag wait">memuat riwayat…</span>';
        const inTop = CFG.dates.map(dt => !!ranks[dt][code]);
        const appearances = inTop.filter(Boolean).length;
        let streak = 0;
        for (let i = 0; i < inTop.length && inTop[i]; i++) streak++;

        if (appearances === 1 && inTop[0]) return '<span class="dd-tag new">BARU 1x</span>';
        if (streak === appearances) {
            return `<span class="dd-tag ${streak >= 5 ? 'old' : 'mid'}">${streak}x berturut</span>`;
        }
        return `<span class="dd-tag mid">${appearances}x dari ${CFG.dates.length} hari</span>`;
    }

    function noteFor(code, rank, ranks, ready) {
        if (!ready) return '';
        const today = CFG.dates[0];
        const prev = CFG.dates[1];
        if (!prev) return '<div class="dd-note">Belum ada riwayat pembanding (rentang 1 hari).</div>';
        const pr = ranks[prev][code];
        if (!pr) return '<div class="dd-note">Kemarin di luar 10 besar.</div>';
        if (rank < pr) return `<div class="dd-note">Naik dari peringkat <strong>#${pr}</strong> kemarin ke <strong>#${rank}</strong></div>`;
        if (rank > pr) return `<div class="dd-note">Turun dari peringkat <strong>#${pr}</strong> kemarin ke <strong>#${rank}</strong></div>`;
        return `<div class="dd-note">Peringkat tetap <strong>#${rank}</strong> dibanding kemarin</div>`;
    }

    function dotsHtml(code, ranks) {
        return '<div class="dd-dots">' + CFG.dates.slice().reverse().map(dt =>
            `<div class="dd-dot"><i class="${ranks[dt][code] ? 'on' : ''}"></i>${dt.slice(8, 10)}</div>`).join('') + '</div>';
    }

    function tableHtml(code) {
        const events = eventsFor(code);
        const rows = events.length === 0
            ? `<tr class="dd-empty-row"><td colspan="4">Tidak ada menit dengan HAKA ≥ Rp ${fmtSingkat(CFG.jumbo)}</td></tr>`
            : events.map(e => {
                const brk = (e.brokers || []).map(b =>
                    `<span class="dd-b"><b>${esc(b.broker)}</b> ${b.pct}%</span>`).join('');
                return `<tr>
                    <td><span class="t">${dm(e.date)}</span><br><span class="t">${esc(e.time)}</span></td>
                    <td>${nf.format(e.lot)}</td>
                    <td class="v">${fmtSingkat(e.value)}</td>
                    <td>${brk}</td>
                </tr>`;
            }).join('');
        const mode = sortMode[code] || 'value';
        return { count: events.length, html: `
            <div class="dd-drop-tools">Urut:
                <select onchange="setSort('${esc(code)}', this.value)">
                    <option value="value" ${mode === 'value' ? 'selected' : ''}>Value terbesar</option>
                    <option value="lot" ${mode === 'lot' ? 'selected' : ''}>Lot terbesar</option>
                    <option value="time" ${mode === 'time' ? 'selected' : ''}>Waktu terbaru</option>
                </select>
            </div>
            <div class="dd-scroll">
                <table class="dd-tbl">
                    <thead><tr><th>Tgl · Jam</th><th>Lot</th><th>Value</th><th>Broker HAKA</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
            ${events.length > 5 ? `<div class="dd-foot">Gulir untuk melihat semua ${events.length} kejadian</div>` : ''}` };
    }

    function cardHtml(it, rank, ranks, ready) {
        const d = it.d, code = it.code;
        const total = d.haka_value + d.haki_value;
        const hp = total > 0 ? (d.haka_value / total) * 100 : 0;
        const tbl = tableHtml(code);
        const partial = CFG.dates.some(dt => store[code][dt] && store[code][dt].partial);

        return `
        <div class="dd-card">
            <div class="dd-row">
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                    <div class="dd-rank">${rank}</div>
                    <span class="dd-code">${esc(code)}</span>
                    ${tagFor(code, ranks, ready)}
                    ${partial ? '<span class="dd-warn" title="Sebagian halaman API tidak terambil">data sebagian</span>' : ''}
                </div>
                <span class="dd-pill">${fmtRatio(it.ratio)}</span>
            </div>
            ${dotsHtml(code, ranks)}
            <div class="dd-bar"><div class="g" style="width:${hp.toFixed(1)}%"></div><div class="r" style="width:${(100 - hp).toFixed(1)}%"></div></div>
            <div class="dd-row" style="font-size:0.72rem;">
                <span style="color:#10b981;">HAKA ${hp.toFixed(0)}%</span>
                <span style="color:var(--loss, #f43f5e);">HAKI ${(100 - hp).toFixed(0)}%</span>
            </div>
            <div class="dd-mini-grid">
                <div class="dd-mini"><div class="l">Value HAKA</div><div class="v" style="color:#10b981;">Rp ${fmtSingkat(d.haka_value)}</div></div>
                <div class="dd-mini"><div class="l">Value HAKI</div><div class="v" style="color:var(--loss, #f43f5e);">Rp ${fmtSingkat(d.haki_value)}</div></div>
            </div>
            ${noteFor(code, rank, ranks, ready)}
            <details class="dd-drop" data-code="${esc(code)}" ${openCodes.has(code) ? 'open' : ''}>
                <summary>
                    <span>HAKA jumbo per 1 menit <span style="color:var(--muted); font-weight:400;">· ${tbl.count} kejadian</span></span>
                    <span class="chev">▾</span>
                </summary>
                ${tbl.html}
            </details>
        </div>`;
    }

    function renderBrokerStrip() {
        const today = CFG.dates[0];
        const agg = {};
        Object.keys(store).forEach(code => {
            const d = store[code][today];
            if (!d) return;
            (d.brokers || []).slice(0, 5).forEach(b => {
                agg[b.broker] = agg[b.broker] || { value: 0, codes: 0 };
                agg[b.broker].value += b.value;
                agg[b.broker].codes++;
            });
        });
        const top = Object.entries(agg).sort((a, b) => b[1].value - a[1].value).slice(0, 3);
        const el = document.getElementById('brokerStrip');
        if (top.length === 0) { el.style.display = 'none'; return; }
        el.style.display = 'block';
        el.innerHTML = `<div class="ttl">Broker paling agresif HAKA (${dm(today)})</div>` +
            top.map(([b, v]) => `<span class="dd-chip"><b>${esc(b)}</b> ${v.codes} emiten · Rp ${fmtSingkat(v.value)}</span>`).join('');
    }

    function render() {
        const ready = doneCount >= totalTasks;
        const ranks = computeRanks();
        const list = top10For(CFG.dates[0]);

        // Kartu teratas terbuka otomatis setelah selesai, kalau pengguna belum mengetuk apa pun
        if (ready && !userToggled && list.length && openCodes.size === 0) openCodes.add(list[0].code);

        document.getElementById('cardsGrid').innerHTML =
            list.map((it, i) => cardHtml(it, i + 1, ranks, ready)).join('');
        document.getElementById('cardsEmpty').style.display = (list.length === 0 && ready) ? 'block' : 'none';
        renderBrokerStrip();
        updateStatus(list.length, ready);
    }

    function updateStatus(shown, ready) {
        document.getElementById('progressBar').style.width = (totalTasks ? (doneCount / totalTasks) * 100 : 0) + '%';
        const oldest = CFG.dates[CFG.dates.length - 1], newest = CFG.dates[0];
        document.getElementById('statusBanner').innerHTML = ready
            ? `💡 Selesai: 10 besar untuk <strong>${dm(newest)}</strong> · riwayat ${CFG.dates.length} hari bursa (${dm(oldest)} sampai ${dm(newest)}) · ${shown} emiten lolos${failedCount ? ` · ${failedCount} data gagal dimuat` : ''}.`
            : `💡 Menganalisis <strong>${doneCount}/${totalTasks}</strong> data emiten-hari… kartu muncul bertahap. Pertama kali bisa agak lama, berikutnya cepat karena di-cache.`;
        if (ready) document.getElementById('progressWrap').style.display = 'none';
    }

    function setSort(code, mode) {
        sortMode[code] = mode;
        render();
    }

    async function analyzeAll() {
        if (totalTasks === 0) return;
        document.getElementById('progressWrap').style.display = 'block';

        // Hari terbaru dulu, supaya 10 besar hari yang dilihat muncul lebih awal
        const tasks = [];
        CFG.dates.forEach(date => CFG.candidates.forEach(code => tasks.push({ code, date })));

        let next = 0;
        const worker = async () => {
            while (next < tasks.length) {
                const t = tasks[next++];
                try {
                    const res = await fetch(CFG.url.replace('__CODE__', t.code).replace('__DATE__', t.date), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (res.ok && !data.error) {
                        store[t.code] = store[t.code] || {};
                        store[t.code][t.date] = data;
                    } else failedCount++;
                } catch (e) { failedCount++; }
                doneCount++;
                render();
            }
        };
        await Promise.all([worker(), worker(), worker(), worker()]);
    }

    // Ingat kartu mana yang dibuka pengguna (event toggle tidak bubble, jadi pakai capture)
    document.getElementById('cardsGrid').addEventListener('toggle', function (e) {
        const el = e.target;
        if (!el.classList || !el.classList.contains('dd-drop')) return;
        userToggled = true;
        const code = el.dataset.code;
        if (el.open) openCodes.add(code); else openCodes.delete(code);
    }, true);

    // Input batas jumbo: format titik ribuan saat mengetik, dibersihkan saat submit
    const jumboInput = document.getElementById('jumboInput');
    jumboInput.addEventListener('input', function () {
        const v = this.value.replace(/\D/g, '');
        this.value = v !== '' ? new Intl.NumberFormat('id-ID').format(v) : '';
    });
    function clearJumboSeparators() { jumboInput.value = jumboInput.value.replace(/\./g, ''); }

    analyzeAll();
</script>
@endpush
