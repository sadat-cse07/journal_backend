<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaperFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'paper_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'version',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function paper()
    {
        return $this->belongsTo(Paper::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}