<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Retailer;
use Illuminate\Http\Request;

class RetailerPortalController extends Controller
{
    protected function retailer(Request $request): Retailer
    {
        return Retailer::where('user_id', $request->user()->id)->firstOrFail();
    }

    /** GET /api/my/received-stock - 4.2 */
    public function receivedStock(Request $request)
    {
        $retailer = $this->retailer($request);

        return response()->json(
            $retailer->giveStocks()->with('items.product')->latest('date')->paginate(20)
        );
    }

    /** GET /api/my/returned-stock - 4.3 */
    public function returnedStock(Request $request)
    {
        $retailer = $this->retailer($request);

        return response()->json(
            $retailer->returnStocks()->with('items.product')->latest('date')->paginate(20)
        );
    }

    /** GET /api/my/payments - 4.4 Paid Amount */
    public function payments(Request $request)
    {
        $retailer = $this->retailer($request);

        return response()->json(
            $retailer->cashPayments()->latest('date')->paginate(20)
        );
    }

    /** GET /api/my/bills - 4.5 */
    public function bills(Request $request)
    {
        $retailer = $this->retailer($request);

        return response()->json(
            $retailer->bills()->with('items.product')->latest('date')->paginate(20)
        );
    }

    /** GET /api/my/bills/{bill} - full bill preview for a retailer */
    public function billShow(Request $request, int $billId)
    {
        $retailer = $this->retailer($request);
        $bill = $retailer->bills()->with('items.product', 'retailer.company')->findOrFail($billId);

        return response()->json($bill);
    }
}
