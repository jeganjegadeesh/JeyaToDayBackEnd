<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'mobile',
        'password',
        'role',
        'commission',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'commission' => 'decimal:2',
    ];

    // Relationships
    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class, 'retailer_id');
    }

    public function returns()
    {
        return $this->hasMany(ReturnStock::class, 'retailer_id');
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'retailer_id');
    }

    // Scopes
    public function scopeRetailers($query)
    {
        return $query->where('role', 'retailer');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isRetailer(): bool
    {
        return $this->role === 'retailer';
    }
}