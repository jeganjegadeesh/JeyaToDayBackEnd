<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetailerLoan extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'retailer_id', 'amount', 'date', 'remarks', 'repaid_amount',
        'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'repaid_amount' => 'decimal:2'];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::created(function (RetailerLoan $loan) {
            $loan->company?->lockOpeningBalance();
        });
    }
}
