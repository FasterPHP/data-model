<?php

declare(strict_types=1);

namespace FasterPHP\DataModel;

final class Sql
{
    public static function ident(string $name): string
    {
        return '`' . str_replace('.', '`.`', $name) . '`';
    }

    public static function placeholder(string $key): string
    {
        return ':' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
    }

    public static function likeWildcards(string $value, string $searchType): string
    {
        return match ($searchType) {
            Repository::STARTS   => $value . '%',
            Repository::ENDS     => '%' . $value,
            Repository::CONTAINS => '%' . $value . '%',
            default              => $value,
        };
    }

    /**
     * Expand an array value into an `IN (...)` SQL fragment with bound parameters.
     *
     * @param string   $column  The column/expression on the left‑hand side.
     * @param mixed[]  $values  The (non‑empty) list of values to match.
     * @param string   $prefix  Parameter name prefix to keep them unique in a query.
     *
     * @return array{0:string,1:array<string,mixed>}  [sqlFragment, params]
     */
    public static function expandIn(string $column, array $values, string $prefix = 'p'): array
    {
        if ($values === []) {
            // Safety‑net: empty IN (...) is always false.
            return ['1 = 0', []];
        }

        $sqlParts = [];
        $params   = [];

        foreach ($values as $i => $value) {
            $param          = ':' . $prefix . $i;
            $sqlParts[]     = $param;
            $params[$param] = $value;
        }

        $fragment = sprintf('%s IN (%s)', $column, implode(',', $sqlParts));

        return [$fragment, $params];
    }
}
