<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'retailer_id', 'date', 'period_from',
        'subtotal', 'commission_percent', 'commission_amount',
        'final_total', 'cash_paid', 'grand_total',
        'created_by', 'updated_by', 'is_deleted',
    ];

    /**
     * settled_amount is always included in the JSON response so the app can
     * show "Rs. X settled" even after grand_total has dropped to 0 - without
     * it, a fully-settled bill would look identical to one that was never
     * owed anything in the first place.
     */
    protected $appends = ['settled_amount'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'period_from' => 'date',
            'subtotal' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'final_total' => 'decimal:2',
            'cash_paid' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function giveStocks()
    {
        return $this->hasMany(GiveStock::class);
    }

    public function returnStocks()
    {
        return $this->hasMany(ReturnStock::class);
    }

    public function cashPayments()
    {
        return $this->hasMany(CashPayment::class);
    }

    public function settlements()
    {
        return $this->hasMany(BillSettlement::class);
    }

    /** Sum of all amounts settled against this bill via the settle() flow. */
    public function getSettledAmountAttribute(): float
    {
        return round((float) $this->settlements()->sum('amount'), 2);
    }
}