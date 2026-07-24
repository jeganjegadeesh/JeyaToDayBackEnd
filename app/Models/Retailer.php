<?php

namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Retailer extends Model
{
    use HasAudit, HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'name', 'phone_number', 'commission',
        'created_by', 'updated_by', 'profile_image', 'is_deleted',
    ];

    protected function casts(): array
    {
        return ['commission' => 'decimal:2'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function giveStocks()
    {
        return $this->hasMany(GiveStock::class);
    }

    public function returnStocks()
    {
        return $this->hasMany(ReturnStock::class);
    }

    public function cashPayments()
    {
        return $this->hasMany(CashPayment::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->profile_image);
    }
}
