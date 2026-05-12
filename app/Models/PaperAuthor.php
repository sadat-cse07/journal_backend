<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperAuthor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'paper_id',
        'user_id',
        'author_order',
        'is_corresponding',
        'contribution',
    ];

    protected $casts = [
        'is_corresponding' => 'boolean',
    ];

    public function paper()
    {
        return $this->belongsTo(Paper::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}