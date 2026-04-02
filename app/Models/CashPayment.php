<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'retailer_id',
        'date',
        'amount',
        'note',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function retailer()
    {
        return $this->belongsTo(User::class, 'retailer_id');
    }
}