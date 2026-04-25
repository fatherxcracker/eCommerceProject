<?php

namespace App\Models;

class User
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'totp_secret',
    ];

    protected $hidden = [
        'password_hash',
        'totp_secret',
    ];

    const ROLE_USER  = 'user';
    const ROLE_ADMIN = 'admin';

    public function adoptionRequests(): HasMany
    {
        return $this->hasMany(AdoptionRequest::class, 'user_id');
    }

    public function adoptionHistory(): HasMany
    {
        return $this->hasMany(AdoptionHistory::class, 'user_id');
    }

    public static function findByEmail(string $email): ?self
    {
        return self::where('email', $email)->first();
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->password_hash);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
