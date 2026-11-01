<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * user table model, manages communication with the database using the root user, 
 * should not be used by any end user, 
 * always use an inherited model with the connection specific to each role.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'profile_photo_path',
        'role',
        'state',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $path = trim((string) ($this->profile_photo_path ?? ''));

        if ($path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($path, 'profile_photos/')) {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return route('perfil.photo.show', ['path' => $path], false);
    }

    /**
     * Professor profile associated with the user.
     */
    public function professor()
    {
        return $this->hasOne(Professor::class, 'user_id', 'id');
    }

    /**
     * Student profile associated with the user.
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'user_id', 'id');
    }

    /**
     * Research staff profile associated with the user.
     */
    public function researchstaff()
    {
        return $this->hasOne(ResearchStaff::class, 'user_id', 'id');
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role The role to check
     * @return bool True if user has the specified role
     */
    public function hasRole($role)
    {
        return $this->normalizeRole($this->role) === $this->normalizeRole($role);
    }

    /**
     * Check if the user has any of the specified roles.
     *
     * @param array|string $roles Single role or array of roles to check
     * @return bool True if user has any of the specified roles
     */
    public function hasAnyRole($roles)
    {
        $normalizedCurrentRole = $this->normalizeRole($this->role);

        return collect((array) $roles)
            ->map(fn ($role) => $this->normalizeRole($role))
            ->contains($normalizedCurrentRole);
    }

    protected function normalizeRole($role)
    {
        $role = mb_strtolower(trim((string) $role));

        return match ($role) {
            'committe_leader' => 'committee_leader',
            default => $role,
        };
    }
}
