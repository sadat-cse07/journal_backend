<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'expertise_keywords',
        'max_reviews',
        'current_reviews',
        'availability_status',
        'average_rating',
        'total_reviews_completed',
    ];

    protected $casts = [
        'expertise_keywords' => 'array',
        'max_reviews' => 'integer',
        'current_reviews' => 'integer',
        'total_reviews_completed' => 'integer',
        'average_rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isAvailable(): bool
    {
        return $this->availability_status === 'available' && 
               $this->current_reviews < $this->max_reviews;
    }

    public function incrementReviews(): void
    {
        $this->increment('current_reviews');
        if ($this->current_reviews >= $this->max_reviews) {
            $this->update(['availability_status' => 'busy']);
        }
    }

    public function decrementReviews(): void
    {
        $this->decrement('current_reviews');
        if ($this->current_reviews <= 0) {
            $this->update(['current_reviews' => 0]);
        }
        if ($this->availability_status === 'busy' && $this->current_reviews < $this->max_reviews) {
            $this->update(['availability_status' => 'available']);
        }
    }
}