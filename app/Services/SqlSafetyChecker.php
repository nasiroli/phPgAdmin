<?php

namespace App\Services;

class SqlSafetyChecker
{
    /**
     * Keywords that indicate a statement mutates data or schema (best-effort guard).
     *
     * @var list<string>
     */
    private const MUTATING_PREFIXES = [
        'insert',
        'update',
        'delete',
        'truncate',
        'create',
        'alter',
        'drop',
        'grant',
        'revoke',
        'comment',
    ];

    /**
     * Whether the SQL appears to be read-only (SELECT, EXPLAIN, WITH … SELECT, SHOW, TABLE).
     */
    public function isReadOnlyStatement(string $sql): bool
    {
        $normalized = $this->normalize($sql);

        if ($normalized === '') {
            return false;
        }

        foreach (self::MUTATING_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix.' ')) {
                return false;
            }
        }

        return str_starts_with($normalized, 'select')
            || str_starts_with($normalized, 'with ')
            || str_starts_with($normalized, 'explain ')
            || str_starts_with($normalized, 'show ')
            || str_starts_with($normalized, 'table ');
    }

    private function normalize(string $sql): string
    {
        $trimmed = trim($sql);

        if ($trimmed === '') {
            return '';
        }

        $withoutComments = preg_replace('/--[^\n]*\n?/', ' ', $trimmed) ?? $trimmed;
        $withoutBlockComments = preg_replace('/\/\*.*?\*\//s', ' ', $withoutComments) ?? $withoutComments;

        return strtolower(trim(preg_replace('/\s+/', ' ', $withoutBlockComments) ?? ''));
    }
}
