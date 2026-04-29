<?php

namespace App\Models;

class Category extends Model
{
    public function pets(): array
    {
        return Pet::findByCategory((int) $this->id);
    }
}
