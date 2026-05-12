<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'review_id',
        'file_name',
        'file_path',
        'file_size',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}