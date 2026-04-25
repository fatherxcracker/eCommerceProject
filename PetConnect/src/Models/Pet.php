<?php

namespace App\Models;

class Pet 
{
    protected $table = 'pets';

    protected $fillable = [
        'name',
        'species',
        'breed',
        'age',
        'size',
        'location',
        'description',
        'image',
        'status',
        'category_id',
    ];

    const STATUS_AVAILABLE = 'available';
    const STATUS_ADOPTED   = 'adopted';
    const STATUS_PENDING   = 'pending';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function adoptionRequests(): HasMany
    {
        return $this->hasMany(AdoptionRequest::class, 'pet_id');
    }

    public static function findByCategory(int $categoryId)
    {
        return self::where('category_id', $categoryId)->get();
    }

    public static function search(string $query)
    {
        return self::where('name', 'LIKE', "%{$query}%")
            ->orWhere('breed', 'LIKE', "%{$query}%")
            ->orWhere('species', 'LIKE', "%{$query}%")
            ->get();
    }

    public static function filter(array $params)
    {
        $q = self::query();

        if (!empty($params['breed']))    $q->where('breed', $params['breed']);
        if (!empty($params['age']))      $q->where('age', $params['age']);
        if (!empty($params['size']))     $q->where('size', $params['size']);
        if (!empty($params['location'])) $q->where('location', 'LIKE', "%{$params['location']}%");
        if (!empty($params['category'])) $q->where('category_id', $params['category']);

        return $q->get();
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }
}
