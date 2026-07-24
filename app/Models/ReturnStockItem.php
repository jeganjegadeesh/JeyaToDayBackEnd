<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnStockItem extends Model
{
    protected $fillable = ['return_stock_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function returnStock()
    {
        return $this->belongsTo(ReturnStock::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
