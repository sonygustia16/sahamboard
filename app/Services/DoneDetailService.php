<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Menarik done-detail (tiap transaksi) dari API arjum, lalu meringkasnya per emiten per hari:
 *  - total HAKA / HAKI (value, lot, jumlah transaksi)
 *  - broker pembeli (HAKA) terbesar
 *  - HAKA per 1 menit (beserta broker di menit itu) dan HAKI per 1 menit,
 *    keduanya hanya yang value-nya >= MINUTE_FLOOR supaya cache tetap kecil.
 *
 * HAKA = action "BUY", HAKI = action "SELL". Hanya papan RG yang dihitung,
 * dan transaksi crossing (buyer == seller) dibuang.
 *
 * Hasil di-cache sementara (tabel cache Laravel yang sudah ada), bukan penyimpanan permanen.
 */
class DoneDetailService
{
    /** Batas terendah value per menit yang disimpan. Batas "jumbo" dari filter tidak bisa di bawah ini. */
    public const MINUTE_FLOOR = 500_000_000;

    private const DEFAULT_PER_PAGE = 100; // sesuai contoh di dokumentasi API; bisa diubah lewat config services.arjum.per_page
    private const MAX_PAGES = 400;  // batas pengaman per emiten per hari (BBCA ~400 halaman @100 baris)
    private const POOL_SIZE = 5;    // jumlah halaman yang ditarik paralel

    public function day(string $code, string $date): array
    {
        $ttl = $date < now()->toDateString()
            ? now()->addDays(7)        // hari lampau: data sudah final
            : now()->addSeconds(120);  // hari ini: masih bergerak

        return Cache::remember("done_detail:v3:{$code}:{$date}", $ttl, fn () => $this->build($code, $date));
    }

    private function build(string $code, string $date): array
    {
        $first = $this->fetchPage($code, $date, 1);

        $totalPages = max(1, (int) ($first['total_pages'] ?? 1));
        $partial = false;
        if ($totalPages > self::MAX_PAGES) {
            $totalPages = self::MAX_PAGES;
            $partial = true;
        }

        $agg = [
            'haka_value' => 0.0, 'haki_value' => 0.0,
            'haka_lot' => 0, 'haki_lot' => 0,
            'haka_trx' => 0, 'haki_trx' => 0,
            'brokers' => [], 'minutes' => [], 'haki_minutes' => [],
        ];

        $this->consume($agg, $first['data'] ?? []);

        for ($p = 2; $p <= $totalPages; $p += self::POOL_SIZE) {
            $batch = range($p, min($p + self::POOL_SIZE - 1, $totalPages));

            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn ($pg) => $pool->as((string) $pg)
                    ->withHeaders($this->headers())
                    ->acceptJson()
                    ->timeout(30)
                    ->get($this->url(), $this->params($code, $date, $pg)),
                $batch
            ));

            foreach ($batch as $pg) {
                $res = $responses[(string) $pg] ?? null;
                if (!($res instanceof Response) || !$res->ok()) {
                    $partial = true;
                    continue;
                }
                $this->consume($agg, $res->json('data') ?? []);
            }
        }

        return $this->finalize($agg, $partial);
    }

    private function consume(array &$agg, array $rows): void
    {
        foreach ($rows as $r) {
            if (($r['market_board'] ?? '') !== 'RG') {
                continue;
            }

            $buyer  = $r['buyer'] ?? '';
            $seller = $r['seller'] ?? '';
            if ($buyer !== '' && $buyer === $seller) {
                continue; // crossing satu broker, bukan tekanan beli/jual asli
            }

            $value  = (float) ($r['value_raw'] ?? 0);
            $lot    = (int) ($r['qty_num'] ?? 0);
            $action = strtoupper((string) ($r['action'] ?? ''));
            $m      = substr((string) ($r['time'] ?? ''), 0, 5); // HH:MM

            if ($action === 'BUY') {
                $agg['haka_value'] += $value;
                $agg['haka_lot'] += $lot;
                $agg['haka_trx']++;

                $b = $buyer !== '' ? $buyer : '-';
                $agg['brokers'][$b]['value'] = ($agg['brokers'][$b]['value'] ?? 0) + $value;
                $agg['brokers'][$b]['lot']   = ($agg['brokers'][$b]['lot'] ?? 0) + $lot;

                if ($m !== '') {
                    $agg['minutes'][$m]['value'] = ($agg['minutes'][$m]['value'] ?? 0) + $value;
                    $agg['minutes'][$m]['lot']   = ($agg['minutes'][$m]['lot'] ?? 0) + $lot;
                    $agg['minutes'][$m]['trx']   = ($agg['minutes'][$m]['trx'] ?? 0) + 1;

                    // Broker yang HAKA di menit ini
                    $agg['minutes'][$m]['brokers'][$b]['value'] = ($agg['minutes'][$m]['brokers'][$b]['value'] ?? 0) + $value;
                    $agg['minutes'][$m]['brokers'][$b]['lot']   = ($agg['minutes'][$m]['brokers'][$b]['lot'] ?? 0) + $lot;
                }
            } elseif ($action === 'SELL') {
                $agg['haki_value'] += $value;
                $agg['haki_lot'] += $lot;
                $agg['haki_trx']++;

                if ($m !== '') {
                    $agg['haki_minutes'][$m]['value'] = ($agg['haki_minutes'][$m]['value'] ?? 0) + $value;
                    $agg['haki_minutes'][$m]['lot']   = ($agg['haki_minutes'][$m]['lot'] ?? 0) + $lot;
                }
            }
        }
    }

    private function finalize(array $agg, bool $partial): array
    {
        // Broker pembeli terbesar sepanjang hari
        $brokers = [];
        foreach ($agg['brokers'] as $code => $v) {
            $brokers[] = ['broker' => $code, 'value' => $v['value'], 'lot' => $v['lot']];
        }
        usort($brokers, fn ($a, $b) => $b['value'] <=> $a['value']);

        // Menit HAKA yang value-nya >= MINUTE_FLOOR (tanpa batas jumlah)
        $hakaMinutes = [];
        foreach ($agg['minutes'] as $time => $v) {
            if ($v['value'] < self::MINUTE_FLOOR) {
                continue;
            }

            $list = [];
            foreach ($v['brokers'] as $code => $bv) {
                $list[] = ['broker' => $code, 'value' => $bv['value']];
            }
            usort($list, fn ($a, $b) => $b['value'] <=> $a['value']);
            $top3 = array_map(fn ($b) => [
                'broker' => $b['broker'],
                'pct'    => (int) round($b['value'] / $v['value'] * 100),
            ], array_slice($list, 0, 3));

            $hakaMinutes[] = [
                'time'    => $time,
                'value'   => $v['value'],
                'lot'     => $v['lot'],
                'trx'     => $v['trx'],
                'brokers' => $top3,
            ];
        }
        usort($hakaMinutes, fn ($a, $b) => $b['value'] <=> $a['value']);

        // Menit HAKI yang value-nya >= MINUTE_FLOOR
        $hakiMinutes = [];
        foreach ($agg['haki_minutes'] as $time => $v) {
            if ($v['value'] >= self::MINUTE_FLOOR) {
                $hakiMinutes[] = ['time' => $time, 'value' => $v['value'], 'lot' => $v['lot']];
            }
        }

        return [
            'haka_value'   => $agg['haka_value'],
            'haki_value'   => $agg['haki_value'],
            'haka_lot'     => $agg['haka_lot'],
            'haki_lot'     => $agg['haki_lot'],
            'haka_trx'     => $agg['haka_trx'],
            'haki_trx'     => $agg['haki_trx'],
            'brokers'      => array_slice($brokers, 0, 10),
            'haka_minutes' => $hakaMinutes,
            'haki_minutes' => $hakiMinutes,
            'partial'      => $partial,
        ];
    }

    private function fetchPage(string $code, string $date, int $page): array
    {
        $res = Http::withHeaders($this->headers())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 300, throw: false)
            ->get($this->url(), $this->params($code, $date, $page));

        if (!$res->ok()) {
            // Sertakan potongan isi jawaban API supaya alasan penolakan terlihat (tanpa tag HTML)
            $detail = mb_substr(trim(strip_tags((string) $res->body())), 0, 160);
            throw new RuntimeException("API done-detail membalas HTTP {$res->status()}" . ($detail !== '' ? ": {$detail}" : ''));
        }

        return $res->json() ?? [];
    }

    private function params(string $code, string $date, int $page): array
    {
        return ['code' => $code, 'date' => $date, 'page' => $page,
                'per_page' => (int) config('services.arjum.per_page', self::DEFAULT_PER_PAGE)];
    }

    private function url(): string
    {
        return config('services.arjum.done_detail_url');
    }

    private function headers(): array
    {
        $key = config('services.arjum.key');
        if (empty($key)) {
            throw new RuntimeException('ARJUM_API_KEY belum diisi di environment.');
        }
        return ['X-API-Key' => $key, 'Accept' => 'application/json'];
    }
}
