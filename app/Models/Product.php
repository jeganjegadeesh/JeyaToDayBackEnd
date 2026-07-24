<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'name', 'type', 'rate', 'created_by', 'updated_by', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
