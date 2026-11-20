<?php

declare(strict_types=1);

namespace Octoverse\Octoreview\Model;

use Octoverse\Octoreview\Exception\MalformedResponseException;

/**
 * Typed reads from a decoded JSON object. A missing or mistyped required field fails loudly
 * instead of becoming a silent default.
 *
 * @internal
 */
final class Data
{
    /** @param array<string, mixed> $data */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value)) {
            throw new MalformedResponseException(sprintf('octoreview: the response has no string %s', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value)) {
            throw new MalformedResponseException(sprintf('octoreview: the response has no integer %s', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    public static function nullableInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /** @param array<string, mixed> $data */
    public static function bool(array $data, string $key): bool
    {
        return ($data[$key] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function map(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    public static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>|null
     */
    public static function nullableList(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? array_values(array_map('strval', $value)) : null;
    }

    /** @param array<string, mixed> $data */
    public static function time(array $data, string $key): \DateTimeImmutable
    {
        return self::nullableTime($data, $key)
            ?? throw new MalformedResponseException(sprintf('octoreview: the response has no timestamp %s', $key));
    }

    /** @param array<string, mixed> $data */
    public static function nullableTime(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new MalformedResponseException(sprintf('octoreview: %s is not a timestamp', $key));
        }
    }
}
