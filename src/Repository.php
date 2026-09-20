<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Paginator\SqlPaginator;
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

    public function getItemWithParams(array $params, array $types = []): ?ItemInterface
    {
        [$sql, $sqlParams] = $this->buildSelectSqlAndParams($params, $types);

        // A paginator of its own keeps the one-row limit off the repository's, which the caller
        // may be holding for its result figures. It inherits the sort so the row picked is the
        // first under whatever ordering the repository is using.
        $data = (new SqlPaginator($this->getPdo(), $this->paginator->getSort()))
            ->setMaxItemsPerPage(1)
            ->setSql($sql)
            ->setParams($sqlParams)
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
     * @param SetInterface $set
     * @param bool $useTransaction Wrap operations in a transaction
     */
    public function saveSet(SetInterface $set, bool $useTransaction = false): void
    {
        if (!$set instanceof $this->setClassName) {
            throw new Exception("Cannot save Set of class '" . get_class($set) . "'");
        }

        if ($useTransaction) {
            $this->pdo->beginTransaction();
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

            if ($useTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Persist a single Item: insert, update, or delete.
     *
     * @param ItemInterface $item
     * @param bool $useTransaction Wrap operation in a transaction
     */
    public function saveItem(ItemInterface $item, bool $useTransaction = false): void
    {
        if (!$item instanceof $this->itemClassName) {
            throw new Exception("Cannot save Item of class '" . get_class($item) . "'");
        }

        if ($useTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            if ($item->isToDelete()) {
                $this->deleteItemIds([$item->getId()]);
            } elseif ($item->isTemp()) {
                $this->insertItem($item);
            } elseif ($item->isDirty()) {
                $this->updateItem($item);
            }

            if ($useTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($useTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
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
        [$sql, $sqlParams] = $this->buildSelectSqlAndParams($params, $types);

        return $this->fetchData($sql, $sqlParams);
    }

    /**
     * Build the SELECT statement for a set of filters, along with its bound parameters.
     *
     * @param array<string, mixed>  $params Filters to apply.
     * @param array<string, string> $types  Search type per filter key.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    protected function buildSelectSqlAndParams(array $params, array $types = []): array
    {
        $sql  = 'SELECT ' . $this->getSelectClause();
        $sql .= ' FROM ' . $this->getFromClause();

        [$whereSql,  $whereParams]  = $this->getWhereSqlAndParams($params, $types);
        if ($whereSql !== '') {
            $sql .= "\nWHERE $whereSql";
        }

        $groupBy = $this->getGroupByClause();
        if ($groupBy !== '') {
            $sql .= "\nGROUP BY $groupBy";
        }

        [$havingSql, $havingParams] = $this->getHavingSqlAndParams($params, $types);
        if ($havingSql !== '') {
            $sql .= "\nHAVING $havingSql";
        }

        return [$sql, $this->mergeParams($whereParams, $havingParams)];
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
            $params = $this->mergeParams($params, $chunk);
        }
        return [implode(' AND ', $fragments), $params];
    }

    /**
     * Merge bound parameters, refusing to overwrite an existing placeholder.
     *
     * Placeholders are injective in the filter key, so an overwrite means a binding would be
     * silently discarded. This is an assertion rather than expected behaviour.
     *
     * @param array<string, mixed> $params The parameters accumulated so far.
     * @param array<string, mixed> $chunk  The parameters to add.
     *
     * @return array<string, mixed>
     *
     * @throws Exception If a placeholder would be overwritten.
     */
    protected function mergeParams(array $params, array $chunk): array
    {
        foreach ($chunk as $placeholder => $value) {
            if (array_key_exists($placeholder, $params)) {
                throw new Exception("Duplicate bound parameter '$placeholder'");
            }
            $params[$placeholder] = $value;
        }
        return $params;
    }

    protected function getComparison(string $key, string $type, mixed $value): array
    {
        if (!isset(self::OPERATORS[$type])) {
            throw new Exception("Unsupported search type '{$type}'");
        }
        // Qualify column with table only if it belongs to the base table
        if (str_contains($key, '.')) {
            $identifier = $key;
        } elseif (
            isset($this->itemClassName::FIELDS[$key])
            || isset($this->itemClassName::FIELDS_READONLY[$key])
        ) {
            $identifier = $this->getTableName() . '.' . $key;
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
    protected function fetchData(string $sql, array $params): array
    {
        return $this->paginator
            ->setSql($sql)
            ->setParams($params)
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
            $item->markItemPersisted($newId);
        } else {
            $item->markItemPersisted();
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
        $item->markItemPersisted();
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

    protected function getPdo(): PDO
    {
        return $this->pdo;
    }
}
