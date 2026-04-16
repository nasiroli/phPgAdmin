<?php

namespace App\Support;

use InvalidArgumentException;

final class PostgresIdentifier
{
    /**
     * Unquoted PostgreSQL identifiers: letters, digits, underscore; must not start with digit.
     */
    public static function isValid(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name);
    }

    /**
     * Double-quote an identifier (escape internal quotes).
     */
    public static function quote(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }

    public static function qualified(string $schema, string $name): string
    {
        return self::quote($schema).'.'.self::quote($name);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $name, string $label = 'Identifier'): void
    {
        if (! self::isValid($name)) {
            throw new InvalidArgumentException("{$label} must match /^[a-zA-Z_][a-zA-Z0-9_]*$/: {$name}");
        }
    }
}
