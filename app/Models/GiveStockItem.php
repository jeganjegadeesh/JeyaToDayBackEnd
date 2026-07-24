<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiveStockItem extends Model
{
    protected $fillable = ['give_stock_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function giveStock()
    {
        return $this->belongsTo(GiveStock::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
