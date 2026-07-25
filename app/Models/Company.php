<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'logo', 'gst_number', 'full_address', 'contact_number',
        'opening_balance', 'opening_balance_date', 'opening_balance_locked', 'is_setup_complete',
        'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'opening_balance_locked' => 'boolean',
            'is_setup_complete' => 'boolean',
        ];
    }

    /**
     * Permanently lock the opening balance fields so they can no longer be
     * edited. Called automatically once the first Expense or RetailerLoan
     * is recorded (see Expense::booted() / RetailerLoan::booted()).
     */
    public function lockOpeningBalance(): void
    {
        if (! $this->opening_balance_locked) {
            $this->forceFill(['opening_balance_locked' => true])->save();
        }
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function retailers()
    {
        return $this->hasMany(Retailer::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}