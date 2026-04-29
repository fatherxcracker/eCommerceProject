<?php

namespace App\Models;

class Pet extends Model
{
    const STATUS_AVAILABLE = 'available';
    const STATUS_ADOPTED   = 'adopted';
    const STATUS_PENDING   = 'pending';

    public static function search(string $query): array
    {
        if (trim($query) === '') {
            return static::all('ORDER BY id DESC');
        }
        return static::findWhere(
            'name LIKE ? OR breed LIKE ? OR species LIKE ?',
            ["%$query%", "%$query%", "%$query%"]
        );
    }

    public static function filter(array $params): array
    {
        $conditions = [];
        $bindings   = [];

        if (!empty($params['breed']))    { $conditions[] = 'breed = ?';       $bindings[] = $params['breed']; }
        if (!empty($params['age']))      { $conditions[] = 'age = ?';         $bindings[] = (int) $params['age']; }
        if (!empty($params['size']))     { $conditions[] = 'size = ?';        $bindings[] = $params['size']; }
        if (!empty($params['location'])) { $conditions[] = 'location LIKE ?'; $bindings[] = '%' . $params['location'] . '%'; }
        if (!empty($params['category'])) { $conditions[] = 'category_id = ?'; $bindings[] = (int) $params['category']; }

        if (empty($conditions)) {
            return static::all('ORDER BY id DESC');
        }

        return static::findWhere(implode(' AND ', $conditions) . ' ORDER BY id DESC', $bindings);
    }

    public static function findByCategory(int $categoryId): array
    {
        return static::findWhere('category_id = ? ORDER BY id DESC', [$categoryId]);
    }

    public function category(): ?Category
    {
        $catId = (int) ($this->bean->category_id ?? 0);
        return $catId ? Category::find($catId) : null;
    }

    public function isAvailable(): bool
    {
        return ($this->bean->status ?? '') === self::STATUS_AVAILABLE;
    }
}
