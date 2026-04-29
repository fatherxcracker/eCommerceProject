<?php

namespace App\Models;

class AdoptionHistory extends Model
{
    protected static function tableName(): string
    {
        return 'adoptionhistory';
    }

    public static function findByUser(int $userId): array
    {
        return static::findWhere('user_id = ? ORDER BY completed_at DESC', [$userId]);
    }

    public function user(): ?User
    {
        return User::find((int) ($this->bean->user_id ?? 0));
    }

    public function pet(): ?Pet
    {
        return Pet::find((int) ($this->bean->pet_id ?? 0));
    }
}
