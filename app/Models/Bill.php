<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $fillable = [
        'retailer_id',
        'date',
        'total_sales',
        'commission',
        'final_amount',
    ];

    protected $casts = [
        'date'         => 'date',
        'total_sales'  => 'decimal:2',
        'commission'   => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    // Relationships
    public function retailer()
    {
        return $this->belongsTo(User::class, 'retailer_id');
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }
}