<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\DoneDetailService;
use Illuminate\Http\Request;

class DoneDetailController extends Controller
{
    // Strategi supaya saham sepi tidak ikut (tahap 1, dari tabel ringkasan_saham).
    // Ubah angka ini kalau mau lebih longgar / lebih ketat.
    private const MIN_AVG_VALUE  = 5_000_000_000; // rata-rata value transaksi/hari minimal Rp 5 M
    private const MIN_AVG_FREQ   = 500;           // rata-rata frekuensi/hari minimal 500x
    private const MAX_DAYS       = 10;            // maksimal hari bursa (riwayat) per pencarian
    private const DEFAULT_JUMBO  = 1_000_000_000; // batas jumbo bawaan: Rp 1 M per menit
    private const MAX_CANDIDATES = 200;           // pengaman: maksimal emiten likuid yang dianalisis

    public function __construct(protected DoneDetailService $doneDetail)
    {
    }

    public function index(Request $request)
    {
        $latest = $this->dateOnly(RingkasanSaham::max('date'));

        $start  = $request->query('start_date') ?: $latest;
        $finish = $request->query('finish_date') ?: $latest;
        if ($start > $finish) {
            [$start, $finish] = [$finish, $start];
        }

        $minRatio  = max(1, (float) $request->query('min_ratio', 3));
        $minTotalM = max(0, (float) $request->query('min_total_m', 3)); // dalam Rp miliar

        // Batas HAKA jumbo per menit: diketik manual (boleh pakai titik ribuan)
        $rawJumbo = preg_replace('/\D/', '', (string) $request->query('jumbo_min', ''));
        $jumbo    = $rawJumbo !== '' ? (float) $rawJumbo : self::DEFAULT_JUMBO;
        $jumboClamped = $jumbo < DoneDetailService::MINUTE_FLOOR;
        $jumbo    = max($jumbo, DoneDetailService::MINUTE_FLOOR);

        // Pakai tanggal bursa yang benar-benar ada di database (otomatis melewati libur/weekend).
        // Urut terbaru dulu: $dates[0] adalah hari yang dilihat (finish date).
        $dates = RingkasanSaham::query()
            ->whereBetween('date', [$start, $finish])
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date')
            ->map(fn ($d) => $this->dateOnly($d))
            ->take(self::MAX_DAYS)
            ->values()
            ->all();

        // Opsional untuk uji coba: /screening/done-detail?max=10 membatasi jumlah emiten yang dianalisis
        $maxCandidates = (int) $request->query('max', 0);
        $maxCandidates = ($maxCandidates > 0 && $maxCandidates < self::MAX_CANDIDATES) ? $maxCandidates : self::MAX_CANDIDATES;

        $candidates = [];
        if (!empty($dates)) {
            $candidates = RingkasanSaham::query()
                ->whereIn('date', $dates)
                ->selectRaw('stock_code, SUM(value) as tv')
                ->groupBy('stock_code')
                ->havingRaw('AVG(value) >= ?', [self::MIN_AVG_VALUE])
                ->havingRaw('AVG(frequency) >= ?', [self::MIN_AVG_FREQ])
                ->orderByDesc('tv')
                ->limit($maxCandidates)
                ->pluck('stock_code')
                ->all();
        }

        return view('screens.done_detail', [
            'startDate'    => $start,
            'finishDate'   => $finish,
            'dates'        => $dates,
            'candidates'   => $candidates,
            'maxCandidates' => self::MAX_CANDIDATES,
            'minRatio'     => $minRatio,
            'minTotalM'    => $minTotalM,
            'jumbo'        => $jumbo,
            'jumboClamped' => $jumboClamped,
            'jumboFloor'   => DoneDetailService::MINUTE_FLOOR,
            'maxDays'      => self::MAX_DAYS,
        ]);
    }

    /**
     * JSON ringkasan satu emiten untuk satu hari. Dipanggil satu-satu oleh JS
     * supaya tiap request ringan dan progress terlihat bertahap.
     */
    public function analyze(Request $request, string $stockCode, string $date)
    {
        set_time_limit(120);

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $stockCode));

        if ($code === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['error' => 'Kode atau tanggal tidak valid.'], 422);
        }

        try {
            $day = $this->doneDetail->day($code, $date);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json($day + ['code' => $code, 'date' => $date]);
    }

    private function dateOnly($d): string
    {
        return substr((string) $d, 0, 10);
    }
}
