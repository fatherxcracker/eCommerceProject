<?php

namespace App\Models;

use RedBeanPHP\R;

class AdoptionRequest extends Model
{
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    protected static function tableName(): string
    {
        return 'adoptionrequest';
    }

    public static function findByUser(int $userId): array
    {
        return static::findWhere('user_id = ? ORDER BY submitted_at DESC', [$userId]);
    }

    public static function findByPet(int $petId): array
    {
        return static::findWhere('pet_id = ?', [$petId]);
    }

    public static function rejectOtherPending(int $petId, int $exceptId): void
    {
        $others = static::findWhere(
            'pet_id = ? AND id != ? AND status = ?',
            [$petId, $exceptId, self::STATUS_PENDING]
        );
        foreach ($others as $req) {
            $req->updateStatus(self::STATUS_REJECTED);
        }
    }

    public function user(): ?User
    {
        return User::find((int) ($this->bean->user_id ?? 0));
    }

    public function pet(): ?Pet
    {
        return Pet::find((int) ($this->bean->pet_id ?? 0));
    }

    public function updateStatus(string $status): void
    {
        $this->bean->status      = $status;
        $this->bean->reviewed_at = date('Y-m-d H:i:s');
        R::store($this->bean);
    }
}
