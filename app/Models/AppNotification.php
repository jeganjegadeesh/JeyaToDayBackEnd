<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = [
        'company_id', 'type', 'title', 'body', 'data', 'reference_id', 'reference_type',
    ];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function recipients()
    {
        return $this->hasMany(AppNotificationRecipient::class);
    }
}
