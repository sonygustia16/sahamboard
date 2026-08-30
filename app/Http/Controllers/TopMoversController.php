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
        $period = $request->query('period', 'daily') === 'weekly' ? 'weekly' : 'daily';

        $availableLatest = RingkasanSaham::max('date');

        // Tanggal acuan: dari filter user kalau ada & valid, else tanggal terbaru.
        // Divalidasi terhadap tanggal yang BENERAN ada datanya di ringkasan_saham,
        // supaya tidak error kalau user pilih tanggal libur/weekend/belum ada data.
        $requestedDate = $request->query('date');
        $refDate = $this->resolveValidDate($requestedDate, $availableLatest);

        $topGainer = $this->topMovers($refDate, true);
        $topLoser  = $this->topMovers($refDate, false);

        $startDate = $period === 'weekly'
            ? $this->tradingDaysAgo($refDate, self::WEEKLY_TRADING_DAYS)
            : $refDate;

        [$topAkum, $topDist] = $this->topAccDist($refDate, $startDate, $refDate);

        return view('screens.top_movers', [
            'date'      => $refDate,
            'latestDate'=> $availableLatest,
            'period'    => $period,
            'startDate' => $startDate,
            'topGainer' => $topGainer,
            'topLoser'  => $topLoser,
            'topAkum'   => $topAkum,
            'topDist'   => $topDist,
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

        // PENTING: jangan pakai broker_limit di sini. API mengurutkan broker dari
        // net value TERBESAR ke terkecil, jadi broker_limit=N cuma ambil N broker
        // net-BUY terbesar — broker net-SELL besar (di ujung daftar) malah kepotong,
        // sehingga hasilnya selalu keliatan "net buy semua". Ambil SEMUA broker,
        // baru kita pisahkan top buyer & top seller sendiri di bawah.
        $batchResults = $this->broker->getBrokerSummaryBatch($stockCodes, [
            'start_date' => $startDate,
            'end_date'   => $endDate,
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
