<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = ['company_id', 'name', 'created_by', 'updated_by', 'is_deleted'];

    public function expenses()
    {
        return $this->belongsToMany(Expense::class, 'expense_raw_material');
    }
}
