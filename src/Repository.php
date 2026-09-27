<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;
use FasterPhp\DataModel\Sql\SqlUtil;
use PDO;

/**
 * @template TItem of Item
 */
abstract class Repository implements RepositoryInterface
{
    /* -------------------------------
     * Model metadata – override in subclass
     * ----------------------------- */
    protected const DB_NAME    = '';
    protected const TABLE_NAME = '';

    /* -------------------------------
     * Search operators
     * ----------------------------- */
    public const EQUALS            = 'equals';
    public const NOT_EQUALS        = 'not equals';
    public const STARTS            = SqlUtil::STARTS;
    public const ENDS              = SqlUtil::ENDS;
    public const CONTAINS          = SqlUtil::CONTAINS;
    public const GREATER           = 'greater';
    public const GREATER_OR_EQUALS = 'greater or equals';
    public const LESS              = 'less';
    public const LESS_OR_EQUALS    = 'less or equals';

    public const OPERATORS = [
        self::EQUALS            => '=',
        self::NOT_EQUALS        => '!=',
        self::STARTS            => 'LIKE',
        self::ENDS              => 'LIKE',
        self::CONTAINS          => 'LIKE',
        self::GREATER           => '>',
        self::GREATER_OR_EQUALS => '>=',
        self::LESS              => '<',
        self::LESS_OR_EQUALS    => '<=',
    ];

    /* -------------------------------
     * Instance state
     * ----------------------------- */
    protected PDO $pdo;
    protected SqlPaginator $paginator;

    /** @var class-string<TItem> */
    protected string $itemClassName;
    /** @var class-string<Set<TItem>> */
    protected string $setClassName;

    /**
     * Items awaiting markItemPersisted() until an owned transaction commits; null when none is owned.
     *
     * @var list<array{Item, mixed}>|null
     */
    private ?array $pendingPersisted = null;

    /* -------------------------------
     * Construction
     * ----------------------------- */
    public function __construct(PDO $pdo, SqlPaginator|Sort|null $paginatorOrSort = null)
    {
        $this->pdo = $pdo;
        if ($paginatorOrSort instanceof SqlPaginator) {
            // A paginator the caller built is used exactly as given, static defaults included.
            $this->paginator = $paginatorOrSort;
        } else {
            // A paginator nobody asked for is unlimited: application-wide defaults must not reach it.
            $this->paginator = (new SqlPaginator($pdo, $paginatorOrSort))
                ->setMaxItemsPerPage(null);
        }

        $this->itemClassName = ClassNameUtil::getItemClassName(static::class);
        $this->setClassName  = ClassNameUtil::getSetClassName(static::class);
    }

    /* -------------------------------
     * Fluent configurators
     * ----------------------------- */
    public function setSort(?Sort $sort): static
    {
        $this->paginator->setSort($sort);
        return $this;
    }

    public function setMaxItemsPerPage(?int $max): static
    {
        $this->paginator->setMaxItemsPerPage($max);
        return $this;
    }

    /* -------------------------------
     * Metadata helpers
     * ----------------------------- */
    public function getDbName(): string
    {
        if (empty(static::DB_NAME)) {
            throw new Exception('Database name not set');
        }
        return static::DB_NAME;
    }

    public function getTableName(): string
    {
        if (empty(static::TABLE_NAME)) {
            throw new Exception('Table name not set');
        }
        return static::TABLE_NAME;
    }

    public function getIdField(): string
    {
        if (empty($this->itemClassName::ID_FIELD)) {
            throw new Exception('Table ID field not set');
        }
        return $this->itemClassName::ID_FIELD;
    }

    /* -------------------------------
     * Public retrieval API
     * ----------------------------- */
    public function getItemWithId(mixed $id): ?ItemInterface
    {
        return $this->getItemWithParams([
            $this->getTableName() . '.' . $this->getIdField() => $id,
        ]);
    }

    /**
     * Get the first Item matching the given parameters, or null when none match.
     *
     * A sort given here orders this lookup alone and replaces the repository's sort for it; the
     * repository's sort is never changed. Without one, the lookup uses the repository's sort.
     */
    public function getItemWithParams(array $params, array $types = [], ?Sort $sort = null): ?ItemInterface
    {
        // A paginator of its own keeps the one-row limit off the repository's, which the caller
        // may be holding for its result figures. It takes the per-call sort when one is given,
        // otherwise the repository's, so the row picked is the first under that ordering; the
        // repository's sort is only read, never written.
        $data = (new SqlPaginator($this->getPdo(), $sort ?? $this->paginator->getSort()))
            ->setMaxItemsPerPage(1)
            ->setQuery($this->buildRetrievalQuery($params, $types))
            ->getItems();

        if (empty($data)) {
            return null;
        }
        return $this->createItem($data[0]);
    }

    public function getSetOfAll(): SetInterface
    {
        return $this->createSet($this->getDataWithParams([]));
    }

    public function getSetWithParams(array $params, array $types = []): SetInterface
    {
        return $this->createSet($this->getDataWithParams($params, $types));
    }

    /* -------------------------------
     * Item / Set factories (override if needed)
     * ----------------------------- */
    protected function createItem(array $data): Item
    {
        return new $this->itemClassName($data, isTemp: false);
    }

    protected function createSet(array $data): Set
    {
        return new $this->setClassName($data);
    }

    /**
     * Persist a Set: insert new, update dirty, delete removed.
     *
     * With $useTransaction true, the repository begins a transaction only if none is active on the
     * connection, and commits or rolls back only a transaction it began. Items are then marked persisted
     * after the commit; on rollback they keep their pre-save state, so saving them again repeats every
     * write. If a transaction is already active, the save joins it: nothing is begun, committed or rolled
     * back, a failure propagates to the transaction's owner, and Items are marked persisted as each
     * statement succeeds, so a new Item's id is available before the owner commits. A caller rolling back
     * a transaction of its own must discard or reload the Items saved within it.
     *
     * @param SetInterface $set
     * @param bool $useTransaction Wrap operations in a transaction, or join the one already active
     */
    public function saveSet(SetInterface $set, bool $useTransaction = false): void
    {
        if (!$set instanceof $this->setClassName) {
            throw new Exception("Cannot save Set of class '" . get_class($set) . "'");
        }

        // Decided once: at commit or rollback time inTransaction() would report our own transaction.
        $ownsTransaction = $useTransaction && !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
            $this->pendingPersisted = [];
        }

        try {
            $idsToDelete = [];
            foreach ($set->getRawData() as $item) {
                if (!is_object($item)) {
                    continue;
                } elseif ($item->isToDelete()) {
                    $idsToDelete[] = $item->getId();
                } elseif ($item->isTemp()) {
                    $this->insertItem($item);
                } elseif ($item->isDirty()) {
                    $this->updateItem($item);
                }
            }
            if (!empty($idsToDelete)) {
                $this->deleteItemIds($idsToDelete);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pendingPersisted = null;
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($ownsTransaction) {
            $this->flushPendingPersisted();
        }
    }

    /**
     * Persist a single Item: insert, update, or delete.
     *
     * With $useTransaction true, the repository begins a transaction only if none is active on the
     * connection, and commits or rolls back only a transaction it began. The Item is then marked
     * persisted after the commit; on rollback it keeps its pre-save state, so saving it again repeats the
     * write. If a transaction is already active, the save joins it: nothing is begun, committed or rolled
     * back, a failure propagates to the transaction's owner, and the Item is marked persisted as soon as
     * its statement succeeds, so a new Item's id is available before the owner commits. A caller rolling
     * back a transaction of its own must discard or reload the Items saved within it.
     *
     * @param ItemInterface $item
     * @param bool $useTransaction Wrap operation in a transaction, or join the one already active
     */
    public function saveItem(ItemInterface $item, bool $useTransaction = false): void
    {
        if (!$item instanceof $this->itemClassName) {
            throw new Exception("Cannot save Item of class '" . get_class($item) . "'");
        }

        // Decided once: at commit or rollback time inTransaction() would report our own transaction.
        $ownsTransaction = $useTransaction && !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
            $this->pendingPersisted = [];
        }

        try {
            if ($item->isToDelete()) {
                $this->deleteItemIds([$item->getId()]);
            } elseif ($item->isTemp()) {
                $this->insertItem($item);
            } elseif ($item->isDirty()) {
                $this->updateItem($item);
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction) {
                $this->pendingPersisted = null;
                $this->pdo->rollBack();
            }
            throw $e;
        }

        if ($ownsTransaction) {
            $this->flushPendingPersisted();
        }
    }

    /* -------------------------------
     * Field list helper – quoted identifiers
     * ----------------------------- */
    protected function getFieldList(): string
    {
        $table      = $this->getTableName();
        $idField    = $this->itemClassName::ID_FIELD;
        $idInternal = $this->itemClassName::ID_INTERNAL;

        $fields = array_keys(array_merge(
            $this->itemClassName::FIELDS,
            $this->itemClassName::FIELDS_READONLY,
        ));

        // Prepend the id column explicitly — it is no longer in FIELDS
        $parts = [
            SqlUtil::ident("$table.$idField") . ' AS ' . SqlUtil::ident($idInternal),
        ];
        foreach ($fields as $field) {
            $parts[] = SqlUtil::ident("$table.$field");
        }
        return implode(', ', $parts);
    }

    /* -------------------------------
     * SQL clause getters – override piecemeal for joins/aliases
     * ----------------------------- */
    protected function getSelectClause(): string
    {
        return $this->getFieldList();
    }

    protected function getFromClause(): string
    {
        return SqlUtil::ident($this->getTableName());
    }

    protected function getGroupByClause(): string
    {
        return $this->itemClassName::FIELDS_AGGREGATE !== []
            ? SqlUtil::ident($this->getTableName() . '.' . $this->getIdField())
            : '';
    }

    /* -------------------------------
     * Core data retrieval pipeline
     * ----------------------------- */
    protected function getDataWithParams(array $params, array $types = []): array
    {
        return $this->fetchData($this->buildRetrievalQuery($params, $types));
    }

    /**
     * Build the repository's base query, before any filters are applied.
     *
     * This is the coarse extension point: a subclass may override it to return a wholly
     * hand-written query, which still receives the repository's filtering, sorting, pagination and
     * Item construction. The default composes the clause hooks below, so a subclass that overrides
     * only one of those keeps working unchanged.
     *
     * The hook receives no filters, and the query it returns must not depend on which retrieval
     * asked for it: anything that varies per call is a filter. The repository applies the caller's
     * filters to whatever query is returned, ANDing them onto its WHERE and HAVING clauses, so an
     * override cannot cause a filter to be omitted. Filters on declared fields are qualified with
     * the table name, so the query must expose the base table under that name, not an alias.
     *
     * @return SqlQuery
     */
    protected function buildSelectQuery(): SqlQuery
    {
        $groupBy = $this->getGroupByClause();

        return new SqlQuery(
            new SqlFragment($this->getSelectClause()),
            new SqlFragment($this->getFromClause()),
            null,
            $groupBy !== '' ? new SqlFragment($groupBy) : null,
        );
    }

    /**
     * Build the query for one retrieval: the base query with the caller's filters applied.
     *
     * Private so that no subclass can bypass it: the filters reach the SQL whatever the query hook
     * returns. The where and having hooks remain the place to inject or translate filters.
     *
     * @param array<string, mixed>  $params Filters to apply.
     * @param array<string, string> $types  Search type per filter key.
     *
     * @return SqlQuery
     *
     * @throws Exception If a filter binds a parameter the base query binds to a different value.
     */
    private function buildRetrievalQuery(array $params, array $types = []): SqlQuery
    {
        [$whereSql,  $whereParams]  = $this->getWhereSqlAndParams($params, $types);
        [$havingSql, $havingParams] = $this->getHavingSqlAndParams($params, $types);

        return $this->buildSelectQuery()
            ->andWhere(new SqlFragment($whereSql, $whereParams))
            ->andHaving(new SqlFragment($havingSql, $havingParams));
    }

    /* -------------------------------
     * WHERE / HAVING helpers
     * ----------------------------- */
    protected function getWhereSqlAndParams(array $params, array $types = []): array
    {
        return $this->getArgsSqlAndParams(
            array_diff_key($params, $this->itemClassName::FIELDS_AGGREGATE),
            $types
        );
    }

    protected function getHavingSqlAndParams(array $params, array $types = []): array
    {
        return $this->getArgsSqlAndParams(
            array_intersect_key($params, $this->itemClassName::FIELDS_AGGREGATE),
            $types
        );
    }

    protected function getArgsSqlAndParams(array $filters, array $types = []): array
    {
        $fragments = [];
        $params    = [];
        foreach ($filters as $key => $value) {
            $searchType = $types[$key] ?? self::EQUALS;
            [$sql, $chunk] = $this->getComparison($key, $searchType, $value);
            $fragments[] = $sql;
            foreach ($chunk as $placeholder => $bound) {
                // Placeholders are injective in the filter key, so a clash within one clause means
                // a binding would be silently discarded. This is an assertion, not expected.
                if (array_key_exists($placeholder, $params)) {
                    throw new Exception("Duplicate bound parameter '$placeholder'");
                }
                $params[$placeholder] = $bound;
            }
        }
        return [implode(' AND ', $fragments), $params];
    }

    protected function getComparison(string $key, string $type, mixed $value): array
    {
        if (!isset(self::OPERATORS[$type])) {
            throw new Exception("Unsupported search type '{$type}'");
        }
        // Qualify column with table only if it belongs to the base table: the ID column, under its
        // own name or the reserved id (a select alias, which WHERE cannot reference), and the
        // declared fields. The placeholder is still derived from the key as given.
        $idField = $this->itemClassName::ID_FIELD;
        $column  = ($key === $this->itemClassName::ID_INTERNAL && $idField !== '') ? $idField : $key;
        if (str_contains($key, '.')) {
            $identifier = $key;
        } elseif (
            ($idField !== '' && $column === $idField)
            || isset($this->itemClassName::FIELDS[$column])
            || isset($this->itemClassName::FIELDS_READONLY[$column])
        ) {
            $identifier = $this->getTableName() . '.' . $column;
        } else {
            $identifier = $key;
        }
        $safeKey     = SqlUtil::ident($identifier);
        $placeholder = SqlUtil::placeholder($key);
        $params      = [];

        $hasNull = false;
        $nonNull = [];
        if (is_array($value)) {
            $value   = array_unique($value);
            $hasNull = in_array(null, $value, true);
            $nonNull = array_values(array_filter($value, static fn($v) => $v !== null));
            if (count($value) === 1) {
                $value = $value[0];
            } elseif (count($value) === 2 && $hasNull) {
                $value = $nonNull[0] ?? null;
            }
        }

        // Array → IN (...) with params
        if ($type === self::EQUALS && is_array($value)) {
            if ($nonNull !== []) {
                [$frag, $inParams] = SqlUtil::expandIn($safeKey, $nonNull, trim($placeholder, ':') . '_');
                $sql = "($frag" . ($hasNull ? " OR $safeKey IS NULL)" : ')');
                return [$sql, $inParams];
            }
            if ($hasNull) {
                return ["$safeKey IS NULL", []];
            }
            // Empty array matches no rows, using the same fragment as an empty IN (...)
            return SqlUtil::expandIn($safeKey, []);
        }

        // Null scalar: compared using SQL null semantics, never bound as a parameter
        if ($value === null) {
            if ($type === self::EQUALS) {
                return ["$safeKey IS NULL", []];
            }
            if ($type === self::NOT_EQUALS) {
                return ["$safeKey IS NOT NULL", []];
            }
        }

        // Scalar / LIKE
        $op   = self::OPERATORS[$type];
        $val  = is_null($value) ? null : SqlUtil::likeWildcards((string)$value, $type);
        $sql  = "$safeKey $op $placeholder";
        if ($hasNull) {
            $sql = "($sql OR $safeKey IS NULL)";
        }
        $params[$placeholder] = $val;

        return [$sql, $params];
    }

    /* -------------------------------
     * Core fetch via paginator
     * ----------------------------- */
    protected function fetchData(SqlQuery $query): array
    {
        return $this->paginator
            ->setQuery($query)
            ->getItems();
    }

    /* -------------------------------
     * Persistence helpers
     * ----------------------------- */
    protected function insertItem(Item $item): void
    {
        $sqlValues = $item->getSqlValues(false);

        // Build portable INSERT INTO (cols) VALUES (...) syntax
        $columns = array_keys($sqlValues);
        $idents  = array_map([SqlUtil::class, 'ident'], $columns);
        $placeholders = array_map(fn($name) => ':' . $name, $columns);

        $sql = 'INSERT INTO ' . SqlUtil::ident($this->getTableName())
             . ' (' . implode(', ', $idents) . ')'
             . ' VALUES (' . implode(', ', $placeholders) . ')';

        $params = [];
        foreach ($sqlValues as $name => $value) {
            $params[':' . $name] = $value;
        }

        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        if (empty($item->getId())) {
            $newId = $this->getPdo()->lastInsertId();
            if (empty($newId)) {
                throw new Exception('Insert succeeded but lastInsertId() returned no value');
            }
            $this->markPersisted($item, $newId);
        } else {
            $this->markPersisted($item);
        }
    }

    protected function updateItem(Item $item): void
    {
        $sqlValues = $item->getChangedSqlValues();
        [$pairs, $params] = $this->buildSetList($sqlValues, '');
        $params[':id'] = $item->getId();
        $sql = 'UPDATE ' . SqlUtil::ident($this->getTableName())
             . ' SET ' . implode(', ', $pairs)
             . ' WHERE ' . SqlUtil::ident($this->getIdField()) . ' = :id';
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        $this->markPersisted($item);
    }

    protected function deleteItemIds(array $ids): void
    {
        [$inSql, $inParams] = SqlUtil::expandIn(
            SqlUtil::ident($this->getIdField()),
            $ids,
            'del_'
        );
        $sql = 'DELETE FROM ' . SqlUtil::ident($this->getTableName())
             . ' WHERE ' . $inSql;
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($inParams);
    }

    protected function buildSetList(array $fieldSqlValues, string $prefix = ''): array
    {
        $pairs  = [];
        $params = [];
        foreach ($fieldSqlValues as $name => $value) {
            $ph = ':' . $prefix . $name;
            $pairs[]     = SqlUtil::ident($name) . ' = ' . $ph;
            $params[$ph] = $value;
        }
        return [$pairs, $params];
    }

    /**
     * Mark an Item persisted now, or once the owned transaction commits if one is in progress.
     *
     * @param mixed $id Generated id, captured straight after the INSERT since later inserts overwrite it
     */
    private function markPersisted(Item $item, mixed $id = null): void
    {
        if ($this->pendingPersisted === null) {
            $item->markItemPersisted($id);
        } else {
            $this->pendingPersisted[] = [$item, $id];
        }
    }

    private function flushPendingPersisted(): void
    {
        $pending = $this->pendingPersisted ?? [];
        $this->pendingPersisted = null;
        foreach ($pending as [$item, $id]) {
            $item->markItemPersisted($id);
        }
    }

    protected function getPdo(): PDO
    {
        return $this->pdo;
    }
}
