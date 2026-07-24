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
}
