<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashReportService;
use Illuminate\Http\Request;

class CashReportController extends Controller
{
    public function __construct(protected CashReportService $service) {}

    /** GET /api/reports/cash?filter=today|yesterday|this_week|this_month|custom&from=&to= */
    public function index(Request $request)
    {
        $data = $request->validate([
            'filter' => 'sometimes|in:today,yesterday,this_week,this_month,custom',
            'from' => 'sometimes|date',
            'to' => 'sometimes|date',
        ]);

        $company = $request->user()->company;

        if (! $company) {
            return response()->json([
                'message' => 'No company is associated with this user.',
            ], 422);
        }

        return response()->json($this->service->build(
            $company,
            $data['filter'] ?? 'today',
            $data['from'] ?? null,
            $data['to'] ?? null,
        ));
    }
}
