<?php

namespace App\Models;

class AdoptionHistory 
{
    protected $table = 'adoption_history';

    protected $fillable = [
        'user_id',
        'pet_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

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
}
