<?php

namespace App\Models;

class AdoptionRequest
{
    protected $table = 'adoption_requests';

    protected $fillable = [
        'user_id',
        'pet_id',
        'status',
        'message',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at'  => 'datetime',
    ];

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class, 'pet_id');
    }

    public static function findByUser(int $userId)
    {
        return self::where('user_id', $userId)->with('pet')->get();
    }

    public static function findByPet(int $petId)
    {
        return self::where('pet_id', $petId)->with('user')->get();
    }

    public function updateStatus(string $status): bool
    {
        $this->status      = $status;
        $this->reviewed_at = now();
        return $this->save();
    }
}
