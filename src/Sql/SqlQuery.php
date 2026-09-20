<?php

/**
 * SQL query value object.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

use FasterPhp\DataModel\Exception;

/**
 * The clauses of a SELECT statement, held as one value.
 *
 * Select and from are required; where, group by and having are optional, and an absent clause is
 * distinguishable from one whose fragment is empty. The query is immutable: with() derives a new
 * instance rather than mutating, so a query handed to another object cannot be altered by it and
 * its SQL can never be temporarily out of step with its parameters.
 */
final readonly class SqlQuery
{
    /**
     * Passed to with() in place of a clause to remove that clause from the derived query.
     */
    public const NONE = false;

    /**
     * @param SqlClause      $select  The select list, without the SELECT keyword.
     * @param SqlClause      $from    The from source, without the FROM keyword.
     * @param SqlClause|null $where   The where condition, or null if the query has none.
     * @param SqlClause|null $groupBy The grouping, or null if the query has none.
     * @param SqlClause|null $having  The having condition, or null if the query has none.
     */
    public function __construct(
        private SqlClause $select,
        private SqlClause $from,
        private ?SqlClause $where = null,
        private ?SqlClause $groupBy = null,
        private ?SqlClause $having = null,
    ) {
    }

    public function getSelect(): SqlClause
    {
        return $this->select;
    }

    public function getFrom(): SqlClause
    {
        return $this->from;
    }

    public function getWhere(): ?SqlClause
    {
        return $this->where;
    }

    public function getGroupBy(): ?SqlClause
    {
        return $this->groupBy;
    }

    public function getHaving(): ?SqlClause
    {
        return $this->having;
    }

    /**
     * Derive a new query with one or more clauses replaced.
     *
     * A clause left as null is carried over unchanged. An optional clause given as self::NONE is
     * removed from the derived query. The original query is never modified.
     *
     * @param SqlClause|null       $select  Replacement select list, or null to carry over.
     * @param SqlClause|null       $from    Replacement from source, or null to carry over.
     * @param SqlClause|false|null $where   Replacement, self::NONE to remove, or null to carry over.
     * @param SqlClause|false|null $groupBy Replacement, self::NONE to remove, or null to carry over.
     * @param SqlClause|false|null $having  Replacement, self::NONE to remove, or null to carry over.
     *
     * @return self
     */
    public function with(
        ?SqlClause $select = null,
        ?SqlClause $from = null,
        SqlClause|false|null $where = null,
        SqlClause|false|null $groupBy = null,
        SqlClause|false|null $having = null,
    ): self {
        return new self(
            $select ?? $this->select,
            $from ?? $this->from,
            self::derive($where, $this->where),
            self::derive($groupBy, $this->groupBy),
            self::derive($having, $this->having),
        );
    }

    /**
     * Render the query as its complete SQL together with every parameter that SQL binds.
     *
     * The two are returned as one value, so SQL can never be obtained without the parameters
     * belonging to it. Clauses are rendered in SELECT, FROM, WHERE, GROUP BY, HAVING order, and an
     * absent clause contributes neither its keyword nor its parameters.
     *
     * @return SqlClause
     *
     * @throws Exception If two clauses bind the same parameter name to different values.
     */
    public function render(): SqlClause
    {
        $sql    = 'SELECT ' . $this->select->getSql() . ' FROM ' . $this->from->getSql();
        $params = [];
        $source = [];

        $this->collectParams($params, $source, 'SELECT', $this->select);
        $this->collectParams($params, $source, 'FROM', $this->from);

        foreach (['WHERE' => $this->where, 'GROUP BY' => $this->groupBy, 'HAVING' => $this->having] as $kw => $clause) {
            if (is_null($clause)) {
                continue;
            }
            $sql .= "\n$kw " . $clause->getSql();
            $this->collectParams($params, $source, $kw, $clause);
        }

        return new SqlClause($sql, $params);
    }

    /**
     * Resolve a with() argument against the clause currently held.
     *
     * @param SqlClause|false|null $replacement The replacement, self::NONE, or null.
     * @param SqlClause|null       $current     The clause currently held.
     *
     * @return SqlClause|null
     */
    private static function derive(SqlClause|false|null $replacement, ?SqlClause $current): ?SqlClause
    {
        if (is_null($replacement)) {
            return $current;
        }
        return self::NONE === $replacement ? null : $replacement;
    }

    /**
     * Accumulate one clause's parameters, refusing to let a binding be silently discarded.
     *
     * Two clauses binding the same name to the same value are accepted, since nothing is lost.
     * Binding it to different values is not, because one of them would be dropped.
     *
     * @param array<string, mixed>  $params The parameters accumulated so far.
     * @param array<string, string> $source The clause each accumulated parameter came from.
     * @param string                $keyword The keyword naming the clause being collected.
     * @param SqlClause             $clause  The clause being collected.
     *
     * @return void
     *
     * @throws Exception If the clause rebinds an accumulated name to a different value.
     */
    private function collectParams(array &$params, array &$source, string $keyword, SqlClause $clause): void
    {
        foreach ($clause->getParams() as $name => $value) {
            if (array_key_exists($name, $params) && $params[$name] !== $value) {
                throw new Exception(sprintf(
                    "Parameter '%s' is bound to different values by the %s clause and the %s clause",
                    $name,
                    $source[$name],
                    $keyword,
                ));
            }
            $params[$name] = $value;
            $source[$name] ??= $keyword;
        }
    }
}
