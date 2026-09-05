<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

final class Sql
{
    /**
     * Quote an identifier, escaping any embedded backtick by doubling it.
     *
     * Each dot-separated segment is quoted independently.
     *
     * @param string $name The identifier to quote.
     *
     * @return string
     */
    public static function ident(string $name): string
    {
        $segments = array_map([self::class, 'quoteSegment'], explode('.', $name));
        return implode('.', $segments);
    }

    /**
     * Quote a single identifier segment.
     *
     * @param string $segment The segment to quote.
     *
     * @return string
     */
    private static function quoteSegment(string $segment): string
    {
        return '`' . str_replace('`', '``', $segment) . '`';
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
