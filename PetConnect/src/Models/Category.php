<?php

namespace App\Models;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = ['name'];

    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class, 'category_id');
    }
}
