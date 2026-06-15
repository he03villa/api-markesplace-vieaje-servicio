<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAbout extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'avatar',
        'bio',
        'address',
        'birth_date',
        'gender',
        'occupation',
        'education',
        'interests',
        'languages',
        'social_links',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'social_links' => 'array',
        'interests' => 'array',
        'languages' => 'array',
    ];

    // Accessors para URLs completas
    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }
        
        // Si ya es una URL completa (por ejemplo, de Google/Facebook)
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }
        
        return asset('storage/' . $this->avatar);
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }
        
        // Formatear número de teléfono (ejemplo básico)
        $phone = preg_replace('/[^0-9]/', '', $this->phone);
        
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($phone, 0, 3), 
                substr($phone, 3, 3), 
                substr($phone, 6, 4)
            );
        }
        
        return $this->phone;
    }

    // Scopes útiles
    public function scopeHasPhone($query): mixed
    {
        return $query->whereNotNull('phone');
    }

    public function scopeHasAvatar($query): mixed
    {
        return $query->whereNotNull('avatar');
    }

    public function scopeByGender($query, string $gender): mixed
    {
        return $query->where('gender', $gender);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
