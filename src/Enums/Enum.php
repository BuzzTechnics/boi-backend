<?php

namespace Boi\Backend\Enums;

use InvalidArgumentException;
use ReflectionClass;

/**
 * Lightweight const-backed enum base. Apps extend the concrete enums
 * (e.g. {@see ApplicationStatus}) and may add program-specific cases.
 */
abstract class Enum
{
    public static function toArray(): array
    {
        $reflection = new ReflectionClass(static::class);

        return $reflection->getConstants();
    }

    public static function names(): array
    {
        return array_keys(static::toArray());
    }

    public static function values(): array
    {
        return array_values(static::toArray());
    }

    public static function hasValue(string $value): bool
    {
        return in_array($value, static::values());
    }

    public static function hasName(string $name): bool
    {
        return in_array($name, static::names());
    }

    public static function getValue(string $name): ?string
    {
        return static::toArray()[$name] ?? null;
    }

    public static function getName(string $value): ?string
    {
        return array_search($value, static::toArray()) ?: null;
    }

    public static function random(): string
    {
        $values = static::values();

        return $values[array_rand($values)];
    }

    public static function options(): array
    {
        $options = [];
        foreach (static::toArray() as $name => $value) {
            $options[$value] = static::formatName($name);
        }

        return $options;
    }

    protected static function formatName(string $name): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $name)));
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, static::values(), true);
    }

    public static function tryFrom(string $value): ?static
    {
        return static::isValid($value) ? new static($value) : null;
    }

    public static function from(string $value): static
    {
        if (! static::isValid($value)) {
            throw new InvalidArgumentException("Invalid value '$value' for enum ".get_called_class());
        }

        return new static($value);
    }

    public static function cases(): array
    {
        return array_map(fn ($value) => new static($value), static::values());
    }

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
