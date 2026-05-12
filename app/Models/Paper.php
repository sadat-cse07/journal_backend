<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Paper extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'abstract',
        'keywords',
        'category_id',
        'paper_type',
        'status',
        'submitted_by',
        'editorial_assigned',
        'current_round',
        'submission_date',
        'decision_date',
        'publication_date',
    ];

    protected $casts = [
        'keywords' => 'array',
        'submission_date' => 'datetime',
        'decision_date' => 'datetime',
        'publication_date' => 'datetime',
        'current_round' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($paper) {
            if (empty($paper->uuid)) {
                $paper->uuid = (string) Str::uuid();
            }
        });
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function editorialAssigned()
    {
        return $this->belongsTo(User::class, 'editorial_assigned');
    }

    public function authors()
    {
        return $this->hasMany(PaperAuthor::class)->orderBy('author_order');
    }

    public function files()
    {
        return $this->hasMany(PaperFile::class);
    }

    public function reviewRounds()
    {
        return $this->hasMany(ReviewRound::class)->orderBy('round_number');
    }

    public function currentReviewRound()
    {
        return $this->hasOne(ReviewRound::class)
                    ->where('round_number', $this->current_round);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}