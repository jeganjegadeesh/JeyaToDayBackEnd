<?php
namespace App\Models;

use App\Traits\HasAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasAudit, HasFactory, Notifiable;

    protected $fillable = [
        'company_id', 'name', 'phone_number', 'password', 'type', 'commission',
        'theme', 'language', 'font_size', 'profile_image', 'created_by', 'updated_by', 'is_deleted',
    ];

    protected $hidden = ['password', 'remember_token'];

    // so profile_image_url auto-appears in JSON responses
    protected $appends = ['profile_image_url'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'commission' => 'decimal:2',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function passwordResetRequests()
    {
        return $this->hasMany(PasswordResetRequest::class);
    }

    public function notificationRecipients()
    {
        return $this->hasMany(AppNotificationRecipient::class);
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isManager(): bool
    {
        return $this->type === 'manager';
    }

    public function isRetailer(): bool
    {
        return $this->type === 'retailer';
    }

    public function canManage(): bool
    {
        return in_array($this->type, ['admin', 'manager']);
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