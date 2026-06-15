<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'reviewer_id',
        'reviewed_user_id',
        'reviewable_type',
        'reviewable_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewedUser()
    {
        return $this->belongsTo(User::class, 'reviewed_user_id');
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'review_likes');
    }
    public function helpfulVotes()
    {
        return $this->belongsToMany(User::class, 'review_helpful_votes');
    }
    public function reports()
    {
        return $this->belongsToMany(User::class, 'review_reports')->withPivot('reason');
    }
    public function reply()
    {
        return $this->hasOne(ReviewReply::class);
    }

    public function getCategoryAttribute()
    {
        return match ($this->reviewable_type) {
            ServiceRequest::class => 'Servicio',
            RideRequest::class => 'Viaje',
            default => null,
        };
    }
}
