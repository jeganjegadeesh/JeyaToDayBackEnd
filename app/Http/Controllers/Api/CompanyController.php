<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /** GET /api/company - current user's company */
    public function show(Request $request)
    {
        return response()->json($request->user()->company);
    }

    /** POST /api/company - create (bootstrap) a company. Admin only. */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $company = Company::create($data);

        return response()->json($company, 201);
    }

    /** PUT /api/company/{company} - update details, incl. opening balance. Admin only. */
    public function update(Request $request, Company $company)
    {
        $data = $this->validated($request, true);

        // Once locked (i.e. the first expense or retailer loan has been
        // recorded), opening_balance / opening_balance_date become
        // permanently read-only so past cash reports can't be rewritten.
        if ($company->opening_balance_locked
            && (array_key_exists('opening_balance', $data) || array_key_exists('opening_balance_date', $data))) {
            return response()->json([
                'message' => 'Opening balance is locked because expenses or retailer loans have already been recorded for this company.',
            ], 422);
        }

        $data['updated_by'] = $request->user()->id;

        $company->update($data);

        return response()->json($company);
    }

    protected function validated(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'name' => ($isUpdate ? 'sometimes|' : 'required|').'string|max:255',
            'logo' => 'sometimes|nullable|image|max:2048',
            'gst_number' => 'sometimes|nullable|string|max:50',
            'full_address' => 'sometimes|nullable|string',
            'contact_number' => 'sometimes|nullable|string|max:20',
            'opening_balance' => 'sometimes|numeric',
            'opening_balance_date' => 'sometimes|nullable|date|required_with:opening_balance',
        ];

        $data = $request->validate($rules);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        return $data;
    }
}
