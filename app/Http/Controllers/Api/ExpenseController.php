<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /** GET /api/expenses?date_from=&date_to= */
    public function index(Request $request)
    {
        $query = Expense::with('rawMaterials');

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        return response()->json($query->latest('date')->paginate(20));
    }

    /**
     * POST /api/expenses
     * { date, raw_material_ids: [1,2], amount, remarks }
     * Every expense reduces the cash balance and appears in the Cash Report.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'raw_material_ids' => 'required|array|min:1',
            'raw_material_ids.*' => 'exists:raw_materials,id',
            'amount' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
        ]);

        $expense = DB::transaction(function () use ($data, $request) {
            $expense = Expense::create([
                'company_id' => $request->user()->company_id,
                'date' => $data['date'],
                'amount' => $data['amount'],
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            $expense->rawMaterials()->sync($data['raw_material_ids']);

            return $expense->load('rawMaterials');
        });

        return response()->json($expense, 201);
    }

    public function show(Expense $expense)
    {
        return response()->json($expense->load('rawMaterials'));
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'date' => 'sometimes|date',
            'raw_material_ids' => 'sometimes|array|min:1',
            'raw_material_ids.*' => 'exists:raw_materials,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
        ]);
        $data['updated_by'] = $request->user()->id;

        $expense->update(collect($data)->except('raw_material_ids')->toArray());
        if (isset($data['raw_material_ids'])) {
            $expense->rawMaterials()->sync($data['raw_material_ids']);
        }

        return response()->json($expense->load('rawMaterials'));
    }

    /** Admin only */
    public function destroy(Expense $expense)
    {
        $expense->softDeleteFlag();

        return response()->json(['message' => 'Expense deleted.']);
    }
}
