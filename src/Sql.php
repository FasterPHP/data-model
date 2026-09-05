<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

final class Sql
{
    /**
     * Permitted identifier shape: one or more non-empty segments of ASCII letters, digits and
     * underscores, separated by single dots.
     */
    private const IDENT_PATTERN = '/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*$/';

    /**
     * Get whether a name is a valid identifier shape.
     *
     * @param string $name The name to check.
     *
     * @return boolean
     */
    public static function isValidIdent(string $name): bool
    {
        return 1 === preg_match(self::IDENT_PATTERN, $name);
    }

    /**
     * Quote an identifier, escaping any embedded backtick by doubling it.
     *
     * Each dot-separated segment is quoted independently. The identifier is validated first, so
     * the escaping is a secondary control rather than the primary one.
     *
     * @param string $name The identifier to quote.
     *
     * @return string
     *
     * @throws Exception If the name is not a valid identifier shape.
     */
    public static function ident(string $name): string
    {
        if (!self::isValidIdent($name)) {
            throw new Exception("Invalid SQL identifier '$name'");
        }

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
