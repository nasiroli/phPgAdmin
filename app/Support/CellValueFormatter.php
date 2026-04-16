<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats table cell values for display with optional JSON pretty-printing.
 *
 * @return array{kind: 'json'|'text', plain: string}
 */
final class CellValueFormatter
{
    public static function format(mixed $value): array
    {
        if ($value === null) {
            return ['kind' => 'text', 'plain' => ''];
        }

        if (is_bool($value)) {
            return ['kind' => 'text', 'plain' => $value ? 'true' : 'false'];
        }

        if (is_array($value) || is_object($value)) {
            return [
                'kind' => 'json',
                'plain' => self::prettyJson($value),
            ];
        }

        if (is_string($value)) {
            $trim = trim($value);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[') && function_exists('json_validate') && json_validate($value)) {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                    return [
                        'kind' => 'json',
                        'plain' => self::prettyJson($decoded),
                    ];
                } catch (\JsonException) {
                    return ['kind' => 'text', 'plain' => $value];
                }
            }

            return ['kind' => 'text', 'plain' => $value];
        }

        if (is_int($value) || is_float($value)) {
            return ['kind' => 'text', 'plain' => (string) $value];
        }

        return ['kind' => 'text', 'plain' => (string) $value];
    }

    private static function prettyJson(mixed $decoded): string
    {
        return json_encode(
            $decoded,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }
}
