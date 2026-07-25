<?php

namespace App\Http\Controllers;

use App\Services\BrokerSummaryService;
use Illuminate\Http\Request;

class BrokerSummaryController extends Controller
{
    public function show(Request $request, string $stockCode, BrokerSummaryService $service)
    {
        $data = $service->getBrokerSummary($stockCode, [
            'start_date'   => $request->query('start_date'),
            'end_date'     => $request->query('end_date'),
            'net'          => $request->boolean('net'),
            'broker_limit' => $request->query('broker_limit', 10),
            'level_limit'  => $request->query('level_limit', 8),
            'all_data'     => $request->boolean('all_data'),
        ]);

        if (!$data) {
            return response()->json(['error' => 'Data broker tidak tersedia'], 404);
        }

        return response()->json($data);
    }

    /**
     * Broker Flow overlay: dipanggil dengan daftar tanggal PERSIS yang sama
     * dengan chart Value NR utama (dikirim dari frontend, hasil dari chart-data),
     * supaya index tiap titik dijamin align 1:1 dengan chart yang sudah tampil.
     * Tidak menghitung rentang tanggal sendiri — murni ikut tanggal yang dikirim.
     *
     * Selain net value/volume kumulatif, endpoint ini juga menghitung
     * Buy Avg & Sell Avg KUMULATIF per broker (bval/bvol dan sval/svol
     * dijumlahkan dari hari pertama s/d hari itu, baru dibagi), supaya
     * konsisten dengan garis net kumulatif yang sudah ditampilkan di chart.
     *
     * @param  Request  $request  query: dates=2026-07-01,2026-07-02,...  mode=value|volume  broker_limit=6
     */
    public function flow(Request $request, string $stockCode, BrokerSummaryService $service)
    {
        $stockCode = strtoupper($stockCode);
        $mode = $request->query('mode', 'value');
        $brokerLimit = (int) $request->query('broker_limit', 6);

        $datesParam = $request->query('dates', '');
        $dates = array_values(array_filter(explode(',', $datesParam)));

        if (empty($dates)) {
            return response()->json(['error' => 'Parameter dates wajib diisi'], 422);
        }

        // Batasi maksimal 90 tanggal sekali panggil (proteksi supaya tidak hammer API eksternal)
        $dates = array_slice($dates, -90);

        $dailyBrokerNval = [];   // [date][broker_code] => nval/nvol hari itu
        $dailyBrokerBval = [];   // [date][broker_code] => total value beli hari itu
        $dailyBrokerBvol = [];   // [date][broker_code] => total lot beli hari itu
        $dailyBrokerSval = [];   // [date][broker_code] => total value jual hari itu
        $dailyBrokerSvol = [];   // [date][broker_code] => total lot jual hari itu
        $totalAbsPerBroker = []; // untuk menentukan top N broker paling aktif
        $brokerNames = [];

        foreach ($dates as $date) {
            $daily = $service->getBrokerSummary($stockCode, [
                'start_date' => $date,
                'end_date'   => $date,
                'net'        => true,
                'all_data'   => true,
            ]);

            if (!$daily || empty($daily['brokers'])) {
                $dailyBrokerNval[$date] = [];
                continue;
            }

            foreach ($daily['brokers'] as $b) {
                $code = $b['broker_code'];
                $val = $mode === 'volume' ? (float) $b['nvol'] : (float) $b['nval'];

                $dailyBrokerNval[$date][$code] = $val;
                $dailyBrokerBval[$date][$code] = (float) ($b['bval'] ?? 0);
                $dailyBrokerBvol[$date][$code] = (float) ($b['bvol'] ?? 0);
                $dailyBrokerSval[$date][$code] = (float) ($b['sval'] ?? 0);
                $dailyBrokerSvol[$date][$code] = (float) ($b['svol'] ?? 0);

                $brokerNames[$code] = $b['broker_name'];
                $totalAbsPerBroker[$code] = ($totalAbsPerBroker[$code] ?? 0) + abs($val);
            }
        }

        arsort($totalAbsPerBroker);
        $topBrokerCodes = array_slice(array_keys($totalAbsPerBroker), 0, $brokerLimit);

        $brokerSeries = [];
        foreach ($topBrokerCodes as $code) {
            $cumulative = 0;
            $cumBval = 0;
            $cumBvol = 0;
            $cumSval = 0;
            $cumSvol = 0;

            $series = [];
            $buyAvgSeries = [];
            $sellAvgSeries = [];

            foreach ($dates as $date) {
                $cumulative += $dailyBrokerNval[$date][$code] ?? 0;
                $cumBval += $dailyBrokerBval[$date][$code] ?? 0;
                $cumBvol += $dailyBrokerBvol[$date][$code] ?? 0;
                $cumSval += $dailyBrokerSval[$date][$code] ?? 0;
                $cumSvol += $dailyBrokerSvol[$date][$code] ?? 0;

                $series[] = round($cumulative, 2);
                // Buy/Sell Avg kumulatif = total value kumulatif / total lot kumulatif s/d hari itu
                $buyAvgSeries[] = $cumBvol > 0 ? round($cumBval / $cumBvol, 2) : null;
                $sellAvgSeries[] = $cumSvol > 0 ? round($cumSval / $cumSvol, 2) : null;
            }

            $brokerSeries[] = [
                'broker_code' => $code,
                'broker_name' => $brokerNames[$code] ?? $code,
                'data'        => $series,
                'buy_avg'     => $buyAvgSeries,
                'sell_avg'    => $sellAvgSeries,
            ];
        }

        return response()->json([
            'stock_code' => $stockCode,
            'mode'       => $mode,
            'dates'      => $dates,
            'brokers'    => $brokerSeries,
        ]);
    }
}
