<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EditorialDecision extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'review_round_id',
        'decision_by',
        'decision',
        'comments',
        'made_at',
    ];

    protected $casts = [
        'made_at' => 'datetime',
    ];

    public function reviewRound()
    {
        return $this->belongsTo(ReviewRound::class);
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decision_by');
    }
}