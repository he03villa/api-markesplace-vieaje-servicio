<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'rating',
        'completed_jobs',
        'email_verified_at',
        'has_notification',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['phone', 'avatar_url', 'total_trips', 'total_earned'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rating' => 'float',
            'completed_jobs' => 'integer',
            'has_notification' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    public function rideRequests()
    {
        return $this->hasMany(RideRequest::class, 'driver_id');
    }

    public function ridePassengers()
    {
        return $this->belongsToMany(RideRequest::class, 'ride_passengers')
            ->withPivot(['seats_reserved', 'status', 'price_paid'])
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewed_user_id');
    }

    public function givenReviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function about(): HasOne
    {
        return $this->hasOne(UserAbout::class);
    }

    public function publications()
    {
        return $this->hasMany(Publication::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    // Accessors para mantener compatibilidad
    public function getPhoneAttribute(): ?string
    {
        return $this->about?->phone;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $userAbout = $this->about;
        $photo = $userAbout?->avatar;
        $firstName = $this->name;
        if (empty($photo)) {
            return 'https://ui-avatars.com/api/?name=' . urlencode($firstName) .
                '&color=FFFFFF&background=09090b&rounded=true&size=50';
        }
        return $this->about?->avatar_url;
    }

    public function getBioAttribute(): ?string
    {
        return $this->about?->bio;
    }

    public function getCountReviewsAttribute(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Total de viajes: como conductor + como pasajero
     */
    public function getTotalTripsAttribute(): int
    {
        $asDriver    = $this->rideRequests->count();
        $asPassenger = $this->ridePassengers->count();

        return $asDriver + $asPassenger;
    }

    /**
     * Total ganado: suma de price_paid en viajes como pasajero
     */
    public function getTotalEarnedAttribute(): float
    {
        // Ganancia por viajes (como pasajero que cobró)
        $fromRides = (float) $this->ridePassengers
            ->sum(fn($ride) => $ride->pivot->price_paid ?? 0);

        // Ganancia por servicios (ofertas aceptadas)
        $fromOffers = (float) $this->offers
            ->where('status', 'accepted')
            ->sum('price');

        return $fromRides + $fromOffers;
    }

    // Métodos para actualizar perfil
    public function updateProfileInfo(array $data): bool
    {
        // Actualizar información básica del usuario
        $userData = array_intersect_key($data, array_flip([
            'name',
            'email',
            'password',
            'rating',
            'completed_jobs'
        ]));

        if (!empty($userData)) {
            $this->update($userData);
        }

        // Actualizar información de UserAbout
        $aboutData = array_intersect_key($data, array_flip([
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
            'social_links'
        ]));

        if (!empty($aboutData)) {
            $this->about()->updateOrCreate(['user_id' => $this->id], $aboutData);
        }

        return true;
    }

    // Método para completar un trabajo
    public function completeJob(): self
    {
        $this->increment('completed_jobs');
        $this->updateRating();

        return $this;
    }

    public function updateRating(): void
    {
        $avgRating = $this->reviews()->avg('rating');
        $this->update(['rating' => round($avgRating ?? 0, 2)]);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    // Scopes útiles
    public function scopeWithAbout($query)
    {
        return $query->with('about');
    }

    public function scopeOrderByRating($query, string $direction = 'desc')
    {
        return $query->orderBy('rating', $direction);
    }

    public function scopeOrderByCompletedJobs($query, string $direction = 'desc')
    {
        return $query->orderBy('completed_jobs', $direction);
    }
}
