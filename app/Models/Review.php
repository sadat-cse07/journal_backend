<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'review_round_id',
        'reviewer_id',
        'status',
        'decision',
        'confidential_comments',
        'comments_for_author',
        'comments_for_editor',
        'rating_originality',
        'rating_methodology',
        'rating_clarity',
        'rating_significance',
        'overall_recommendation',
        'assigned_at',
        'due_date',
        'submitted_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'due_date' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function reviewRound()
    {
        return $this->belongsTo(ReviewRound::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function files()
    {
        return $this->hasMany(ReviewFile::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date && now()->gt($this->due_date) && $this->status !== 'completed';
    }
}