<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotificationRecipient extends Model
{
    protected $table = 'app_notification_recipients';

    protected $fillable = ['app_notification_id', 'user_id', 'is_read', 'read_at'];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function notification()
    {
        return $this->belongsTo(AppNotification::class, 'app_notification_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
