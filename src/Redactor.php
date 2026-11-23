<?php

declare(strict_types=1);

namespace Octoverse\Octoreview;

/**
 * Removes query strings (signatures), token path segments, bearer values and service keys from
 * text before it reaches an exception message.
 *
 * @internal
 */
final class Redactor
{
    public static function text(string $text): string
    {
        $text = (string) preg_replace('#(https?://[^\s?"\']+)\?[^\s"\']*#', '$1?<redacted>', $text);
        $text = (string) preg_replace('#/(media-uploads|media-files)/[^/\s?"\']+#', '/$1/<redacted>', $text);
        $text = (string) preg_replace('#(?i)bearer\s+[^\s"\']+#', 'Bearer <redacted>', $text);

        return (string) preg_replace('#ocr_[A-Za-z0-9_\-]+#', 'ocr_<redacted>', $text);
    }

    /**
     * A copy of a decoded JSON value with every string redacted, for structured error fields such
     * as `details` (a free-form `reason` can carry a signed URL).
     */
    public static function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::text($value);
        }
        if (is_array($value)) {
            return array_map(self::value(...), $value);
        }

        return $value;
    }

    public static function url(string $url): string
    {
        return self::text(explode('?', $url, 2)[0]);
    }
}
