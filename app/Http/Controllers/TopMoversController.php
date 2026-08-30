<?php

namespace App\Http\Controllers;

use App\Models\RingkasanSaham;
use App\Services\BrokerSummaryService;
use Illuminate\Http\Request;

class TopMoversController extends Controller
{
    // Threshold sinyal Acc/Dist: net value broker dibanding total value transaksi
    // pada periode yang dipilih. Silakan disesuaikan sesuai pengamatan lapangan.
    const SUPER_THRESHOLD = 15; // %
    const BIG_THRESHOLD   = 5;  // %

    // Mode "Mingguan" = akumulasi N hari bursa terakhir.
    const WEEKLY_TRADING_DAYS = 5;

    // Berapa banyak saham (paling aktif by value) yang di-scan live ke API broker summary
    // tiap load halaman. Dibatasi supaya loading tidak terlalu lama & hemat kuota API harian.
    const SCAN_LIMIT = 40;

    protected BrokerSummaryService $broker;

    public function __construct(BrokerSummaryService $broker)
    {
        $this->broker = $broker;
    }

    public function index(Request $request)
    {
        $availableLatest = RingkasanSaham::max('date');

        // Rentang tanggal fleksibel dari user. Default: end = tanggal terbaru,
        // start = 5 hari bursa sebelum end (mirip mode "Mingguan" sebelumnya),
        // tapi sekarang user bebas atur sendiri berapa hari pun mau dilihat.
        $requestedEnd   = $request->query('end_date');
        $requestedStart = $request->query('start_date');

        $endDate = $this->resolveValidDate($requestedEnd, $availableLatest);

        if (!empty($requestedStart)) {
            $startDate = $this->resolveValidDate($requestedStart, $this->tradingDaysAgo($endDate, self::WEEKLY_TRADING_DAYS));
            // Jaga-jaga kalau user kebalik isi start > end.
            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }
        } else {
            $startDate = $this->tradingDaysAgo($endDate, self::WEEKLY_TRADING_DAYS);
        }

        $topGainer = $this->topMovers($endDate, true);
        $topLoser  = $this->topMovers($endDate, false);

        [$topAkum, $topDist] = $this->topAccDist($endDate, $startDate, $endDate);

        return view('screens.top_movers', [
            'date'       => $endDate,
            'startDate'  => $startDate,
            'endDate'    => $endDate,
            'latestDate' => $availableLatest,
            'topGainer'  => $topGainer,
            'topLoser'   => $topLoser,
            'topAkum'    => $topAkum,
            'topDist'    => $topDist,
        ]);
    }

    /**
     * Pastikan tanggal yang diminta user beneran punya data di ringkasan_saham.
     * Kalau tidak ada (mis. weekend/libur bursa/belum ditarik), mundur ke tanggal
     * terdekat sebelumnya yang ada datanya. Kalau kosong sama sekali, pakai tanggal terbaru.
     */
    private function resolveValidDate(?string $requestedDate, string $fallback): string
    {
        if (empty($requestedDate)) {
            return $fallback;
        }

        $exists = RingkasanSaham::where('date', $requestedDate)->exists();
        if ($exists) {
            return $requestedDate;
        }

        $nearest = RingkasanSaham::where('date', '<=', $requestedDate)->max('date');

        return $nearest ?: $fallback;
    }

    private function topMovers(string $date, bool $gainer, int $limit = 10)
    {
        return RingkasanSaham::query()
            ->where('date', $date)
            ->where('previous', '>', 0)
            ->selectRaw('stock_code, close, previous, ((close - previous) * 100.0 / previous) as change_pct')
            ->orderBy('change_pct', $gainer ? 'desc' : 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Ambil daftar saham paling aktif (by value) pada tanggal terbaru, lalu untuk
     * masing-masing panggil BrokerSummaryService::getBrokerSummary() (service yang
     * sudah dipakai fitur Broker Summary/Broker Flow) dengan start_date/end_date sesuai
     * periode dipilih (all_data=true supaya API agregasi sendiri seluruh rentang itu).
     * Total nval seluruh broker untuk 1 saham dijumlah, lalu dibagi total value
     * transaksi saham itu pada rentang yang sama untuk dapat persentase Acc/Dist.
     */
    private function topAccDist(string $latestDate, string $startDate, string $endDate, int $limit = 10): array
    {
        $activeStocks = RingkasanSaham::query()
            ->where('date', $latestDate)
            ->orderByDesc('value')
            ->limit(self::SCAN_LIMIT)
            ->get(['stock_code', 'close', 'previous']);

        $valuePerStock = RingkasanSaham::query()
            ->whereIn('stock_code', $activeStocks->pluck('stock_code'))
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('stock_code, SUM(value) as total_value')
            ->groupBy('stock_code')
            ->pluck('total_value', 'stock_code');

        $stockCodes = $activeStocks->pluck('stock_code')->all();

        // PENTING: 'all_data' => true WAJIB ada supaya API benar-benar mengagregasi
        // SELURUH rentang start_date..end_date (bukan cuma snapshot 1 hari terakhir).
        // Ini juga otomatis membuat API balikin SEMUA broker tanpa terpotong — pas,
        // karena kita mau pisahkan sendiri top buyer & top seller di bawah, bukan
        // pakai broker_limit yang cuma ambil sisi net-buy terbesar.
        $batchResults = $this->broker->getBrokerSummaryBatch($stockCodes, [
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'all_data'   => true,
        ]);

        $topBrokersEach = 10; // berapa broker teratas dari tiap sisi (buy & sell) yang dihitung
        $rows = collect();

        foreach ($activeStocks as $stock) {
            $result  = $batchResults[$stock->stock_code] ?? null;
            $brokers = $result['brokers'] ?? [];
            if (empty($brokers)) {
                continue;
            }

            // Pisahkan net BUYER (nval positif) & net SELLER (nval negatif), lalu
            // ambil yang paling besar dari masing-masing sisi — supaya sinyal Acc/Dist
            // mencerminkan aktivitas broker BESAR, bukan rata-rata seluruh pasar
            // (yang otomatis selalu ~0 kalau dijumlah semua).
            $buyers  = array_filter($brokers, fn ($b) => ($b['nval'] ?? 0) > 0);
            $sellers = array_filter($brokers, fn ($b) => ($b['nval'] ?? 0) < 0);

            usort($buyers, fn ($a, $b) => ($b['nval'] ?? 0) <=> ($a['nval'] ?? 0));
            usort($sellers, fn ($a, $b) => ($a['nval'] ?? 0) <=> ($b['nval'] ?? 0)); // paling negatif duluan

            $topBuyers  = array_slice($buyers, 0, $topBrokersEach);
            $topSellers = array_slice($sellers, 0, $topBrokersEach);

            $buySum  = array_sum(array_map(fn ($b) => (float) ($b['nval'] ?? 0), $topBuyers));
            $sellSum = array_sum(array_map(fn ($b) => (float) ($b['nval'] ?? 0), $topSellers)); // sudah negatif

            $totalNval = $buySum + $sellSum; // skor bersih dari broker-broker besar saja
            if ($totalNval == 0) {
                continue;
            }

            $totalValue = (float) ($valuePerStock[$stock->stock_code] ?? 0);
            $nvalPct    = $totalValue > 0 ? ($totalNval / $totalValue) * 100 : 0;
            $changePct  = $stock->previous > 0 ? (($stock->close - $stock->previous) / $stock->previous) * 100 : 0;

            $rows->push((object) [
                'stock_code' => $stock->stock_code,
                'close'      => $stock->close,
                'total_nval' => $totalNval,
                'nval_pct'   => $nvalPct,
                'change_pct' => $changePct,
                'label'      => $this->labelFor($nvalPct),
            ]);
        }

        $topAkum = $rows->filter(fn ($r) => $r->total_nval > 0)->sortByDesc('total_nval')->take($limit)->values();
        $topDist = $rows->filter(fn ($r) => $r->total_nval < 0)->sortBy('total_nval')->take($limit)->values();

        return [$topAkum, $topDist];
    }

    private function labelFor(float $nvalPct): string
    {
        if ($nvalPct >= self::SUPER_THRESHOLD)  return 'Super Acc';
        if ($nvalPct >= self::BIG_THRESHOLD)    return 'Big Acc';
        if ($nvalPct <= -self::SUPER_THRESHOLD) return 'Super Dist';
        if ($nvalPct <= -self::BIG_THRESHOLD)   return 'Big Dist';
        return '-';
    }

    private function tradingDaysAgo(string $refDate, int $tradingDays): string
    {
        $date = \Illuminate\Support\Carbon::parse($refDate);
        $counted = 0;
        while ($counted < $tradingDays) {
            $date->subDay();
            if (!$date->isWeekend()) {
                $counted++;
            }
        }
        return $date->toDateString();
    }
}
