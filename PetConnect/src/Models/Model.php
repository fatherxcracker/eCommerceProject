<?php

namespace App\Models;

use RedBeanPHP\R;

abstract class Model
{
    protected object $bean;

    public function __construct(object $bean)
    {
        $this->bean = $bean;
    }

    public function __get(string $name): mixed
    {
        return $this->bean->$name ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->bean->$name = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->bean->$name);
    }

    protected static function tableName(): string
    {
        return strtolower((new \ReflectionClass(static::class))->getShortName());
    }

    public static function find(int $id): ?static
    {
        $bean = R::load(static::tableName(), $id);
        return $bean->id ? new static($bean) : null;
    }

    public static function all(string $sql = ''): array
    {
        $beans = R::findAll(static::tableName(), $sql);
        return array_values(array_map(fn($b) => new static($b), $beans));
    }

    public static function create(array $data): static
    {
        $bean = R::dispense(static::tableName());
        foreach ($data as $key => $value) {
            $bean->$key = $value;
        }
        R::store($bean);
        return new static($bean);
    }

    public static function findOne(string $where, array $bindings = []): ?static
    {
        $bean = R::findOne(static::tableName(), $where, $bindings);
        return $bean ? new static($bean) : null;
    }

    public static function findWhere(string $where, array $bindings = []): array
    {
        $beans = R::find(static::tableName(), $where, $bindings);
        return array_values(array_map(fn($b) => new static($b), $beans));
    }

    public static function count(string $where = '', array $bindings = []): int
    {
        return R::count(static::tableName(), $where ? ' WHERE ' . $where : '', $bindings);
    }

    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->bean->$key = $value;
        }
    }

    public function save(): void
    {
        R::store($this->bean);
    }

    public function delete(): void
    {
        R::trash($this->bean);
    }
}
