<?php

namespace App\Models;

class User extends Model
{
    const ROLE_USER  = 'user';
    const ROLE_ADMIN = 'admin';

    public static function findByEmail(string $email): ?static
    {
        return static::findOne('email = ?', [strtolower(trim($email))]);
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->bean->password_hash ?? '');
    }

    public function isAdmin(): bool
    {
        return ($this->bean->role ?? '') === self::ROLE_ADMIN;
    }
}
