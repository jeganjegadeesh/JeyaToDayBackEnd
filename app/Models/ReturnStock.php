<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnStock extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'retailer_id', 'date', 'is_billed', 'bill_id',
        'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_billed' => 'boolean'];
    }

    public function retailer()
    {
        return $this->belongsTo(Retailer::class);
    }

    public function items()
    {
        return $this->hasMany(ReturnStockItem::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
