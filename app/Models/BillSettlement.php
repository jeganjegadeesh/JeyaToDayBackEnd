<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cash collected from a retailer against a specific bill's outstanding
 * grand total. Kept separate from CashPayment - see the migration
 * docblock for why the two must not be mixed.
 */
class BillSettlement extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'bill_id', 'retailer_id', 'date', 'amount',
        'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }
}