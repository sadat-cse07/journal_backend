<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewRound extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'paper_id',
        'round_number',
        'status',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function paper()
    {
        return $this->belongsTo(Paper::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function editorialDecision()
    {
        return $this->hasOne(EditorialDecision::class);
    }
}