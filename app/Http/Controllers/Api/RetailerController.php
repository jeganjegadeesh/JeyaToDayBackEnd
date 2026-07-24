<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
class RetailerController extends Controller
{
    /** GET /api/retailers?search= */
    public function index(Request $request)
    {
        $query = Retailer::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        return response()->json($query->latest()->paginate(20));
    }

    /** POST /api/retailers - also creates the retailer's login user account */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|unique:users,phone_number',
            'commission' => 'required|numeric|min:0|max:100',
            'profile_image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_image')) {
            $data['profile_image'] = $request->file('profile_image')->store('userAssets', 'public');
        }

        $retailer = DB::transaction(function () use ($data, $request) {
            $user = User::create([
                'company_id' => $request->user()->company_id,
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'password' => Hash::make(config('app.default_user_password', '123456')),
                'type' => 'retailer',
                'commission' => $data['commission'],
                'profile_image' => $data['profile_image'] ?? null,
                'created_by' => $request->user()->id,
            ]);
            return Retailer::create([
                'company_id' => $request->user()->company_id,
                'user_id' => $user->id,
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'commission' => $data['commission'],
                'profile_image' => $data['profile_image'] ?? null,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($retailer, 201);
    }

    public function show(Retailer $retailer)
    {
        return response()->json($retailer);
    }

    /** PUT /api/retailers/{retailer} */
    public function update(Request $request, Retailer $retailer)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|unique:users,phone_number,'.$retailer->user_id,
            'commission' => 'sometimes|numeric|min:0|max:100',
            'profile_image' => 'nullable|image|max:2048',
        ]);
        $data['updated_by'] = $request->user()->id;

        if ($request->hasFile('profile_image')) {
            if ($retailer->profile_image) {
                Storage::disk('public')->delete($retailer->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('userAssets', 'public');
        }

        $retailer->update($data);

        if ($retailer->user_id) {
            $retailer->user()->update(array_intersect_key($data, array_flip(['name', 'phone_number', 'commission', 'profile_image'])));
        }

        return response()->json($retailer);
    }

    /** DELETE /api/retailers/{retailer} - Admin only */
    public function destroy(Retailer $retailer)
    {
        $retailer->softDeleteFlag();
        $retailer->user?->softDeleteFlag();
        return response()->json(['message' => 'Retailer deleted.']);
    }

    public function restore(Retailer $retailer)
    {
        $retailer->restoreFlag();
        $retailer->user?->restoreFlag();
        return response()->json(['message' => 'Retailer restored.']);
    }
}