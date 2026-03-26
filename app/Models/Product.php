<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tamil_name',
        'price',
        'category',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    // Relationships
    public function stockEntryItems()
    {
        return $this->hasMany(StockEntryItem::class);
    }

    public function returnItems()
    {
        return $this->hasMany(ReturnItem::class);
    }

    public function billItems()
    {
        return $this->hasMany(BillItem::class);
    }
}