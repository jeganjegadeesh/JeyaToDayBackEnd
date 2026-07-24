<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashPayment;
use Illuminate\Http\Request;

class CashPaymentController extends Controller
{
    /** GET /api/cash-payments?retailer_id=&date_from=&date_to=&is_billed=&per_page= */
    public function index(Request $request)
    {
        $query = CashPayment::with('retailer');

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        if ($request->filled('is_billed')) {
            $query->where('is_billed', $request->boolean('is_billed'));
        }

        $perPage = (int) $request->input('per_page', 20);

        return response()->json($query->latest('date')->paginate($perPage));
    }

    /** POST /api/cash-payments  { retailer_id, date, amount } */
    public function store(Request $request)
    {
        $data = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        $payment = CashPayment::create($data);

        return response()->json($payment->load('retailer'), 201);
    }

    public function show(CashPayment $cashPayment)
    {
        return response()->json($cashPayment->load('retailer'));
    }

    /** PUT /api/cash-payments/{cashPayment} - blocked once billed */
    public function update(Request $request, CashPayment $cashPayment)
    {
        if ($cashPayment->is_billed) {
            return response()->json(['message' => 'Cannot edit a payment already applied to a bill.'], 422);
        }

        $data = $request->validate([
            'date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0.01',
        ]);
        $data['updated_by'] = $request->user()->id;

        $cashPayment->update($data);

        return response()->json($cashPayment);
    }

    /** DELETE /api/cash-payments/{cashPayment} - Admin only, blocked once billed */
    public function destroy(CashPayment $cashPayment)
    {
        if ($cashPayment->is_billed) {
            return response()->json(['message' => 'Cannot delete a payment already applied to a bill.'], 422);
        }

        $cashPayment->softDeleteFlag();

        return response()->json(['message' => 'Cash payment deleted.']);
    }
}