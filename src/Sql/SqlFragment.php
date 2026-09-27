<?php

/**
 * SQL fragment value object.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

/**
 * A SQL fragment together with the parameters that fragment binds.
 *
 * A fragment may be any piece of SQL, from the body of a single clause up to a complete statement.
 * Holding the SQL and its parameters as one value is what stops them being separated, merged
 * lossily, or observed in a half-updated state. A fragment is immutable: a `readonly` class rejects
 * any write after construction rather than silently accepting it.
 */
final readonly class SqlFragment
{
    /** @var array<string, mixed> */
    public array $params;

    /**
     * @param string               $sql    The SQL fragment.
     * @param array<string, mixed> $params The parameters the fragment binds, keyed by placeholder.
     */
    public function __construct(public string $sql, array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Get the SQL fragment.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Get the parameters the fragment binds.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }
}
