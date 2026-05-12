<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'email',
        'password',
        'affiliation',
        'department',
        'orcid_id',
        'phone',
        'bio',
        'avatar_path',
        'status',
        'created_by',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ACCESSORS
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // RELATIONSHIPS
    public function reviewerProfile()
    {
        return $this->hasOne(ReviewerProfile::class);
    }

    public function submittedPapers()
    {
        return $this->hasMany(Paper::class, 'submitted_by');
    }

    public function assignedPapers()
    {
        return $this->hasMany(Paper::class, 'editorial_assigned');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function decisions()
    {
        return $this->hasMany(EditorialDecision::class, 'decision_by');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // HELPERS
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}