<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    protected $fillable = [
        'bill_id', 'product_id', 'given_qty', 'returned_qty', 'sold_qty', 'rate', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'given_qty' => 'decimal:2',
            'returned_qty' => 'decimal:2',
            'sold_qty' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
