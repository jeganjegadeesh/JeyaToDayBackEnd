<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'date', 'amount', 'remarks', 'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }

    public function rawMaterials()
    {
        return $this->belongsToMany(RawMaterial::class, 'expense_raw_material');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected static function booted(): void
    {
        static::created(function (Expense $expense) {
            $expense->company?->lockOpeningBalance();
        });
    }
}
