<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RetailerLoan;
use Illuminate\Http\Request;

class RetailerLoanController extends Controller
{
    /** GET /api/retailer-loans?retailer_id=&date_from=&date_to= */
    public function index(Request $request)
    {
        $query = RetailerLoan::with('retailer');

        if ($request->filled('retailer_id')) {
            $query->where('retailer_id', $request->retailer_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->latest('date')->paginate(20));
    }

    /**
     * POST /api/retailer-loans  { retailer_id, amount, date, remarks }
     * Loan amount is treated as a cash outflow and reflected in the Cash Report.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'retailer_id' => 'required|exists:retailers,id',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'remarks' => 'nullable|string|max:255',
        ]);
        $data['company_id'] = $request->user()->company_id;
        $data['created_by'] = $request->user()->id;

        $loan = RetailerLoan::create($data);

        return response()->json($loan->load('retailer'), 201);
    }

    public function show(RetailerLoan $retailerLoan)
    {
        return response()->json($retailerLoan->load('retailer'));
    }

    public function update(Request $request, RetailerLoan $retailerLoan)
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
            'remarks' => 'nullable|string|max:255',
            'repaid_amount' => 'sometimes|numeric|min:0',
        ]);
        $data['updated_by'] = $request->user()->id;

        $retailerLoan->update($data);

        return response()->json($retailerLoan);
    }

    /** Admin only */
    public function destroy(RetailerLoan $retailerLoan)
    {
        $retailerLoan->softDeleteFlag();

        return response()->json(['message' => 'Retailer loan deleted.']);
    }
}
