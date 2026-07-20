@extends('layouts.app')

@section('title', 'Watchlist')
@section('page-title', 'Watchlist')
@section('page-subtitle', 'Pantau saham target dengan harga live dan jarak ke target')

@section('content')

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Date</th>
                    <th>Stock Code</th>
                    <th>Live Price</th>
                    <th>Target Price</th>
                    <th>Change</th>
                    <th>Jarak ke Target</th>
                    <th style="width:90px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($watchlistRows as $row)
                    @php
                        $code = $row->stock_code;
                        $livePrice = $livePriceCache[$code] ?? null;
                        $isHit = false;
                        $changeText = '-';
                        $changeClass = 'text-gray';

                        if ($livePrice !== null) {
                            $formattedLive = number_format($livePrice, 0, ',', '.');
                            $gap = $row->target_price - $livePrice;
                            $isHit = $gap <= 0;
                            $gapClass = $isHit ? 'text-green' : 'text-gray';
                            $gapText = $isHit ? '🎯 Tercapai' : number_format($gap, 0, ',', '.') . ' poin';

                            $entryRef = $row->entry ?? null;
                            if ($entryRef && $entryRef > 0) {
                                $diff = $livePrice - $entryRef;
                                $pct = ($diff / $entryRef) * 100;
                                if ($diff > 0) {
                                    $changeText = '+' . number_format($diff, 0, ',', '.') . ' / +' . number_format($pct, 2) . '%';
                                    $changeClass = 'text-green';
                                } elseif ($diff < 0) {
                                    $changeText = number_format($diff, 0, ',', '.') . ' / ' . number_format($pct, 2) . '%';
                                    $changeClass = 'text-red';
                                } else {
                                    $changeText = '0 / 0%';
                                }
                            }
                        } else {
                            $formattedLive = "<span class='status-chip'>Timeout/Limit</span>";
                            $gapClass = 'text-gray';
                            $gapText = '-';
                        }

                        $entryPrice = $row->entry ?? $livePrice ?? 0;
                        $dateFormatted = $row->date ? \Illuminate\Support\Carbon::parse($row->date)->format('d M y') : '-';
                    @endphp
                    <tr class="clickable-row watchlist-row" style="{{ $isHit ? 'box-shadow: inset 3px 0 0 var(--gain);' : '' }}"
                        onclick="openDetailModal({{ $row->id }})"
                        data-id="{{ $row->id }}"
                        data-code="{{ $code }}"
                        data-date="{{ $dateFormatted }}"
                        data-entry="{{ $entryPrice }}"
                        data-entry-lot="{{ $row->entry_lot }}"
                        data-target-price="{{ $row->target_price }}"
                        data-note="{{ $row->note }}"
                        data-fee-beli="{{ $row->fee_beli_pct }}"
                        data-fee-jual="{{ $row->fee_jual_pct }}"
                        data-live="{{ $formattedLive }}"
                    >
                        <td class="text-center"><strong>{{ $loop->iteration }}</strong></td>
                        <td>{{ $dateFormatted }}</td>
                        <td><span class="code-pill">{{ $code }}</span></td>
                        <td class="text-right live-cell">{!! $formattedLive !!}</td>
                        <td class="text-right">{{ number_format($row->target_price, 0, ',', '.') }}</td>
                        <td class="text-center {{ $changeClass }}">{{ $changeText }}</td>
                        <td class="text-right {{ $gapClass }}">{{ $gapText }}</td>
                        <td class="text-center" onclick="event.stopPropagation();">
                            <button type="button" class="icon-btn" title="Edit" onclick="openDetailModal({{ $row->id }})">✏️</button>
                            <button type="button" class="icon-btn" title="Hapus" onclick="openDeleteConfirm({{ $row->id }}, '{{ $code }}')">🗑️</button>
                        </td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="8">Watchlist masih kosong — tambahkan saham target di atas, atau klik ikon ☆ di tabel Filter Lengkap/Screening.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ══ Modal Detail / Edit (klik baris atau ikon pensil) ══ --}}
    <div id="detailModalOverlay" class="modal-overlay" style="display:none;" onclick="closeDetailModal(event)">
        <div class="modal-box" onclick="event.stopPropagation();">
            <div class="modal-header">
                <span id="detailModalTitle" style="font-family:var(--mono); font-weight:700; font-size:1.05rem;"></span>
                <button type="button" class="modal-close" onclick="closeDetailModal()">✕</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; gap:1rem; margin-bottom:1rem; font-size:0.8rem; color:var(--muted);">
                    <span>Tanggal: <strong id="detailDate" style="color:var(--ink);"></strong></span>
                    <span>Live: <strong id="detailLive" style="color:var(--ink);"></strong></span>
                </div>

                <div class="avg-grid">
                    <div class="avg-field">
                        <label>Entry (Close)</label>
                        <input type="text" id="detailEntry" readonly class="avg-readonly avg-highlight-cyan">
                    </div>
                    <div class="avg-field">
                        <label>Entry Lot</label>
                        <input type="number" min="0" id="detailEntryLot" class="avg-input">
                    </div>
                    <div class="avg-field">
                        <label>Target Price</label>
                        <input type="number" step="0.01" min="0" id="detailTargetPrice" class="avg-input">
                    </div>

                    <div class="avg-field">
                        <label class="lbl-red">Entry Avg 1 (-5%)</label>
                        <input type="text" class="avg-readonly" id="detailEntryAvg1" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-red">Entry Avg 2 (-10%)</label>
                        <input type="text" class="avg-readonly" id="detailEntryAvg2" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-red">Entry Avg 3 (-15%)</label>
                        <input type="text" class="avg-readonly" id="detailEntryAvg3" readonly>
                    </div>

                    <div class="avg-field">
                        <label class="lbl-blue">Lot Avg 1 (-5%)</label>
                        <input type="text" class="avg-readonly" id="detailLotAvg1" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-blue">Lot Avg 2 (-10%)</label>
                        <input type="text" class="avg-readonly" id="detailLotAvg2" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-blue">Lot Avg 3 (-15%)</label>
                        <input type="text" class="avg-readonly" id="detailLotAvg3" readonly>
                    </div>

                    <div class="avg-field">
                        <label class="lbl-orange">Avg Price</label>
                        <input type="text" class="avg-readonly avg-highlight-cyan" id="detailAvgPrice" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-orange">CL (Cut Loss)</label>
                        <input type="text" class="avg-readonly" id="detailCl" readonly style="color:var(--loss);">
                    </div>
                    <div class="avg-field">
                        <label class="lbl-orange">Total Lot</label>
                        <input type="text" class="avg-readonly avg-highlight-yellow" id="detailTotalLot" readonly>
                    </div>

                    <div class="avg-field">
                        <label class="lbl-green">Modal</label>
                        <input type="text" class="avg-readonly" id="detailModal" readonly>
                    </div>
                    <div class="avg-field">
                        <label class="lbl-red">Rugi (-4%)</label>
                        <input type="text" class="avg-readonly" id="detailRugi" readonly style="color:var(--loss);">
                    </div>
                    <div class="avg-field"></div>

                    <div class="avg-field">
                        <label>Fee Beli (%)</label>
                        <input type="number" step="0.001" min="0" max="10" id="detailFeeBeli" class="avg-input" placeholder="0.15">
                    </div>
                    <div class="avg-field">
                        <label>Fee Jual (%)</label>
                        <input type="number" step="0.001" min="0" max="10" id="detailFeeJual" class="avg-input" placeholder="0.25">
                    </div>
                    <div class="avg-field"></div>

                    <div class="avg-field">
                        <label>Fee Selisih Beli</label>
                        <input type="text" class="avg-readonly" id="detailFeeSelisihBeli" readonly>
                    </div>
                    <div class="avg-field">
                        <label>Fee Selisih Jual</label>
                        <input type="text" class="avg-readonly" id="detailFeeSelisihJual" readonly>
                    </div>
                    <div class="avg-field"></div>
                </div>

                <div class="avg-field" style="margin-top:0.8rem;">
                    <label>Alasan / Keterangan</label>
                    <textarea id="detailNote" class="avg-input" rows="2" placeholder="Kenapa saham ini masuk watchlist..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <span id="detailSaveStatus" style="font-size:0.75rem; color:var(--muted);">&nbsp;</span>
                <button type="button" class="btn btn-ghost" onclick="closeDetailModal()">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="saveDetailModal()">Simpan</button>
            </div>
        </div>
    </div>

    {{-- ══ Modal Konfirmasi Hapus ══ --}}
    <div id="deleteModalOverlay" class="modal-overlay" style="display:none;" onclick="closeDeleteConfirm(event)">
        <div class="modal-box modal-box-small" onclick="event.stopPropagation();">
            <div class="modal-header">
                <span style="font-weight:700; color:var(--loss);">Hapus dari Watchlist?</span>
                <button type="button" class="modal-close" onclick="closeDeleteConfirm()">✕</button>
            </div>
            <div class="modal-body">
                <p style="color:var(--muted); font-size:0.9rem;">Yakin mau hapus <strong id="deleteStockCode" style="color:var(--ink);"></strong> dari watchlist? Tindakan ini tidak bisa dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="closeDeleteConfirm()">Batal</button>
                <form id="deleteForm" method="POST" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-primary" style="background: var(--loss); border-color: var(--loss);">Ya, Hapus</button>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('body-scripts')
<style>
    .icon-btn { background:none; border:none; cursor:pointer; font-size:1rem; padding:0.2rem 0.35rem; opacity:0.8; }
    .icon-btn:hover { opacity:1; transform:scale(1.15); }

    .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal-box {
        background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
        width: 100%; max-width: 640px; max-height: 88vh; overflow-y: auto;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .modal-box-small { max-width: 420px; }
    .modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.3rem; border-bottom: 1px solid var(--border);
    }
    .modal-close { background:none; border:none; color:var(--muted); font-size:1.1rem; cursor:pointer; }
    .modal-close:hover { color:var(--ink); }
    .modal-body { padding: 1.2rem 1.3rem; }
    .modal-footer {
        display: flex; align-items: center; justify-content: flex-end; gap: 0.6rem;
        padding: 1rem 1.3rem; border-top: 1px solid var(--border);
    }

    .avg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.7rem; }
    @media (max-width: 640px) { .avg-grid { grid-template-columns: repeat(2, 1fr); } }
    .avg-field label { display:block; font-size:0.68rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.03em; margin-bottom:0.25rem; font-weight:600; }
    .avg-field input, .avg-field textarea {
        width:100%; box-sizing:border-box; background: var(--bg); border:1px solid var(--border); border-radius:6px;
        color: var(--ink); padding:0.5rem 0.6rem; font-family: var(--mono); font-size:0.85rem;
    }
    .avg-readonly { color: var(--muted) !important; background: var(--panel-2) !important; cursor:default; }
    .avg-highlight-cyan { color: var(--cyan) !important; font-weight: 700 !important; border-color: rgba(34,211,238,0.4) !important; }
    .avg-highlight-yellow { color: #fbbf24 !important; font-weight: 700 !important; border-color: rgba(245,158,11,0.4) !important; }
    .avg-field label.lbl-red { color: #f43f5e !important; }
    .avg-field label.lbl-blue { color: #38bdf8 !important; }
    .avg-field label.lbl-orange { color: #f97316 !important; }
    .avg-field label.lbl-green { color: #10b981 !important; }
    .avg-input:focus { outline:none; border-color: var(--cyan); }
    .watchlist-row { cursor: pointer; }
</style>
<script>
    const CL_PCT = 0.04; // Cut Loss & Rugi dihitung dari -4% dari Avg Price

    function fmt(num) { return new Intl.NumberFormat('id-ID').format(Math.round(num)); }

    let currentDetailId = null;

    function calcAndRenderDetail(entry, entryLot) {
        const feeBeli = parseFloat(document.getElementById('detailFeeBeli').value) || 0;
        const feeJual = parseFloat(document.getElementById('detailFeeJual').value) || 0;

        const entryAvg1 = entry * 0.95;
        const entryAvg2 = entry * 0.90;
        const entryAvg3 = entry * 0.85;

        const lotAvg1 = entryLot * 2;
        const lotAvg2 = entryLot * 4;
        const lotAvg3 = entryLot * 8;

        const totalLot = entryLot + lotAvg1 + lotAvg2 + lotAvg3;

        let avgPrice = 0;
        if (totalLot > 0) {
            avgPrice = (entry * entryLot + entryAvg1 * lotAvg1 + entryAvg2 * lotAvg2 + entryAvg3 * lotAvg3) / totalLot;
        }

        const modalDasar = avgPrice * totalLot * 100;
        const feeSelisihBeli = modalDasar * (feeBeli / 100); // Nominal Rp fee beli
        const feeSelisihJual = modalDasar * (feeJual / 100); // Nominal Rp fee jual
        const modal = modalDasar + feeSelisihBeli; // Modal + fee beli
        const rugiKotor = modalDasar * -CL_PCT;
        const rugi = rugiKotor - feeSelisihJual; // Rugi diperberat fee jual
        const cl = avgPrice * (1 - CL_PCT);

        document.getElementById('detailEntryAvg1').value = entryLot > 0 ? fmt(entryAvg1) : '-';
        document.getElementById('detailEntryAvg2').value = entryLot > 0 ? fmt(entryAvg2) : '-';
        document.getElementById('detailEntryAvg3').value = entryLot > 0 ? fmt(entryAvg3) : '-';
        document.getElementById('detailLotAvg1').value = entryLot > 0 ? fmt(lotAvg1) : '-';
        document.getElementById('detailLotAvg2').value = entryLot > 0 ? fmt(lotAvg2) : '-';
        document.getElementById('detailLotAvg3').value = entryLot > 0 ? fmt(lotAvg3) : '-';
        document.getElementById('detailAvgPrice').value = entryLot > 0 ? fmt(avgPrice) : '-';
        document.getElementById('detailTotalLot').value = entryLot > 0 ? fmt(totalLot) : '-';
        document.getElementById('detailModal').value = entryLot > 0 ? 'Rp ' + fmt(modal) : '-';
        document.getElementById('detailRugi').value = entryLot > 0 ? 'Rp ' + fmt(rugi) : '-';
        document.getElementById('detailCl').value = entryLot > 0 ? fmt(cl) : '-';
        document.getElementById('detailFeeSelisihBeli').value = entryLot > 0 ? 'Rp ' + fmt(feeSelisihBeli) : '-';
        document.getElementById('detailFeeSelisihJual').value = entryLot > 0 ? 'Rp ' + fmt(feeSelisihJual) : '-';
    }

    function openDetailModal(id) {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) return;

        currentDetailId = id;

        const entry = parseFloat(row.dataset.entry) || 0;
        const entryLot = parseInt(row.dataset.entryLot) || 0;

        document.getElementById('detailModalTitle').textContent = row.dataset.code;
        document.getElementById('detailDate').textContent = row.dataset.date;
        document.getElementById('detailLive').innerHTML = row.dataset.live;
        document.getElementById('detailEntry').value = fmt(entry);
        document.getElementById('detailEntryLot').value = row.dataset.entryLot || '';
        document.getElementById('detailTargetPrice').value = row.dataset.targetPrice || '';
        document.getElementById('detailFeeBeli').value = row.dataset.feeBeli || '';
        document.getElementById('detailFeeJual').value = row.dataset.feeJual || '';
        document.getElementById('detailNote').value = row.dataset.note || '';

        calcAndRenderDetail(entry, entryLot);

        function recalc() {
            calcAndRenderDetail(entry, parseInt(document.getElementById('detailEntryLot').value) || 0);
        }
        document.getElementById('detailEntryLot').oninput = recalc;
        document.getElementById('detailFeeBeli').oninput = recalc;
        document.getElementById('detailFeeJual').oninput = recalc;

        document.getElementById('detailModalOverlay').style.display = 'flex';
    }

    function closeDetailModal(evt) {
        if (evt && evt.target !== evt.currentTarget) return;
        document.getElementById('detailModalOverlay').style.display = 'none';
        currentDetailId = null;
    }

    function saveDetailModal() {
        if (!currentDetailId) return;
        const statusEl = document.getElementById('detailSaveStatus');
        statusEl.textContent = 'Menyimpan...';

        const payload = {
            entry_lot: document.getElementById('detailEntryLot').value || null,
            target_price: document.getElementById('detailTargetPrice').value || 0,
            fee_beli_pct: document.getElementById('detailFeeBeli').value || null,
            fee_jual_pct: document.getElementById('detailFeeJual').value || null,
            note: document.getElementById('detailNote').value,
        };

        fetch(`/watchlist/${currentDetailId}/detail`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                statusEl.textContent = '✓ Tersimpan, memuat ulang...';
                setTimeout(() => window.location.reload(), 500);
            } else {
                statusEl.textContent = 'Gagal menyimpan';
            }
        })
        .catch(() => { statusEl.textContent = 'Gagal menyimpan (offline?)'; });
    }

    function openDeleteConfirm(id, code) {
        document.getElementById('deleteStockCode').textContent = code;
        document.getElementById('deleteForm').action = `/watchlist/${id}`;
        document.getElementById('deleteModalOverlay').style.display = 'flex';
    }

    function closeDeleteConfirm(evt) {
        if (evt && evt.target !== evt.currentTarget) return;
        document.getElementById('deleteModalOverlay').style.display = 'none';
    }
</script>
@endpush
