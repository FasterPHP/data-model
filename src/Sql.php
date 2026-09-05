<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

final class Sql
{
    /* -------------------------------
     * Canonical search types for wildcard matching
     * ----------------------------- */
    public const STARTS   = 'starts';
    public const ENDS     = 'ends';
    public const CONTAINS = 'contains';

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

    /**
     * Get the bound parameter placeholder for a filter key.
     *
     * A key that is already a valid placeholder name is used unchanged. Otherwise sanitisation is
     * lossy, so a deterministic suffix derived from the original key is appended to keep the
     * mapping from key to placeholder injective.
     *
     * @param string $key The filter key.
     *
     * @return string
     */
    public static function placeholder(string $key): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        if ($name !== $key) {
            $name .= '_' . hash('crc32b', $key);
        }
        return ':' . $name;
    }

    public static function likeWildcards(string $value, string $searchType): string
    {
        return match ($searchType) {
            self::STARTS   => $value . '%',
            self::ENDS     => '%' . $value,
            self::CONTAINS => '%' . $value . '%',
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
