<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\Db\Db;
use PDO;

/**
 * @template TItem of Item
 */
abstract class Repository
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
    public const STARTS            = 'starts';
    public const ENDS              = 'ends';
    public const CONTAINS          = 'contains';
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
    protected SqlPaginator $paginator;
    protected Db|PDO $db;

    /** @var class-string<TItem> */
    protected string $itemClassName;
    /** @var class-string<Set<TItem>> */
    protected string $setClassName;

    /* -------------------------------
     * Construction
     * ----------------------------- */
    public function __construct(SqlPaginator|Sort|null $paginatorOrSort = null)
    {
        $this->paginator = $paginatorOrSort instanceof SqlPaginator
            ? $paginatorOrSort
            : new SqlPaginator($paginatorOrSort instanceof Sort ? $paginatorOrSort : null);

        $this->itemClassName = Util::getItemClassName(static::class);
        $this->setClassName  = Util::getSetClassName(static::class);
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

    public function setDb(Db|PDO $db): static
    {
        $this->db = $db;
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
    public function getItemWithId(mixed $id): ?Item
    {
        return $this->getItemWithParams([
            $this->getTableName() . '.' . $this->getIdField() => $id,
        ]);
    }

    public function getItemWithParams(array $params, array $types = []): ?Item
    {
        $set = $this->getSetWithParams($params, $types);
        return $set[0] ?? null;
    }

    public function getSetOfAll(): Set
    {
        return $this->createSet($this->getDataWithParams([]));
    }

    public function getSetWithParams(array $params, array $types = []): Set
    {
        return $this->createSet($this->getDataWithParams($params, $types));
    }

    /* -------------------------------
     * Item / Set factories (override if needed)
     * ----------------------------- */
    protected function createItem(array $data): Item
    {
        return new $this->itemClassName($data);
    }

    protected function createSet(array $data): Set
    {
        return new $this->setClassName($data);
    }

    /**
     * Persist a Set: insert new, update dirty, delete removed.
     */
    public function saveSet(Set $set): void
    {
        if (!$set instanceof $this->setClassName) {
            throw new Exception("Cannot save Set of class '" . get_class($set) . "'");
        }
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
    }

    /**
     * Persist a single Item: insert, update, or delete.
     */
    public function saveItem(Item $item): void
    {
        if (!$item instanceof $this->itemClassName) {
            throw new Exception("Cannot save Item of class '" . get_class($item) . "'");
        }
        if ($item->isToDelete()) {
            $this->deleteItemIds([$item->getId()]);
        } elseif ($item->isTemp()) {
            $this->insertItem($item);
        } elseif ($item->isDirty()) {
            $this->updateItem($item);
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

        $parts = [];
        foreach ($fields as $field) {
            if ($field === $idInternal) {
                $parts[] = Sql::ident("$table.$idField")
                    . ' AS '
                    . Sql::ident($idInternal);
            } else {
                $parts[] = Sql::ident("$table.$field");
            }
        }
        return implode(', ', $parts);
    }

    /* -------------------------------
     * SQL clause builders – override piecemeal for joins/aliases
     * ----------------------------- */
    protected function buildSelectClause(): string
    {
        return $this->getFieldList();
    }

    protected function buildFromClause(): string
    {
        return Sql::ident($this->getTableName());
    }

    protected function buildGroupByClause(): string
    {
        return $this->itemClassName::FIELDS_AGGREGATE !== []
            ? Sql::ident($this->getTableName() . '.' . $this->getIdField())
            : '';
    }

    /* -------------------------------
     * Core data retrieval pipeline
     * ----------------------------- */
    protected function getDataWithParams(array $params, array $types = []): array
    {
        $sql  = 'SELECT ' . $this->buildSelectClause();
        $sql .= ' FROM ' . $this->buildFromClause();

        [$whereSql,  $whereParams]  = $this->getWhereSqlAndParams($params, $types);
        if ($whereSql !== '') {
            $sql .= "\nWHERE $whereSql";
        }

        $groupBy = $this->buildGroupByClause();
        if ($groupBy !== '') {
            $sql .= "\nGROUP BY $groupBy";
        }

        [$havingSql, $havingParams] = $this->getHavingSqlAndParams($params, $types);
        if ($havingSql !== '') {
            $sql .= "\nHAVING $havingSql";
        }

        return $this->fetchData($sql, $whereParams + $havingParams);
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
            [$sql, $chunk] = $this->buildComparison($key, $searchType, $value);
            $fragments[] = $sql;
            $params += $chunk;
        }
        return [implode(' AND ', $fragments), $params];
    }

    protected function buildComparison(string $key, string $type, mixed $value): array
    {
        if (!isset(self::OPERATORS[$type])) {
            throw new Exception("Unsupported search type '{$type}'");
        }
        // Qualify column with table if no explicit alias provided
        $identifier = str_contains($key, '.')
            ? $key
            : $this->getTableName() . '.' . $key;
        $safeKey     = Sql::ident($identifier);
        $placeholder = Sql::placeholder($key);
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
                [$frag, $inParams] = Sql::expandIn($safeKey, $nonNull, trim($placeholder, ':') . '_');
                $sql = "($frag" . ($hasNull ? " OR $safeKey IS NULL)" : ')');
                return [$sql, $inParams];
            }
            return ["$safeKey IS NULL", []];
        }

        // Null scalar
        if ($type === self::EQUALS && $value === null) {
            return ["$safeKey IS NULL", []];
        }

        // Scalar / LIKE
        $op   = self::OPERATORS[$type];
        $val  = Sql::likeWildcards((string)$value, $type);
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
            ->setDb($this->getDb())
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
        [$pairs, $params] = $this->buildSetList($sqlValues, '');
        $sql = 'INSERT INTO ' . Sql::ident($this->getTableName())
             . ' SET ' . implode(', ', $pairs);
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        if (empty($item->getId())) {
            $newId = $this->getDb()->lastInsertId();
            if ($newId) {
                $item->setId($newId);
            }
        }
        $item->clearOriginalValues();
    }

    protected function updateItem(Item $item): void
    {
        $sqlValues = $item->getChangedSqlValues();
        [$pairs, $params] = $this->buildSetList($sqlValues, '');
        $params[':id'] = $item->getId();
        $sql = 'UPDATE ' . Sql::ident($this->getTableName())
             . ' SET ' . implode(', ', $pairs)
             . ' WHERE ' . Sql::ident($this->getIdField()) . ' = :id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        $item->clearOriginalValues();
    }

    protected function deleteItemIds(array $ids): void
    {
        [$inSql, $inParams] = Sql::expandIn(
            Sql::ident($this->getIdField()),
            $ids,
            'del_'
        );
        $sql = 'DELETE FROM ' . Sql::ident($this->getTableName())
             . ' WHERE ' . $inSql;
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($inParams);
    }

    protected function buildSetList(array $fieldSqlValues, string $prefix = ''): array
    {
        $pairs  = [];
        $params = [];
        foreach ($fieldSqlValues as $name => $value) {
            $ph = ':' . $prefix . $name;
            $pairs[]     = Sql::ident($name) . ' = ' . $ph;
            $params[$ph] = $value;
        }
        return [$pairs, $params];
    }

    /* -------------------------------
     * DB accessor (lazy)
     * ----------------------------- */
    protected function getDb(): Db|PDO
    {
        if (!isset($this->db) && class_exists(Db::class)) {
            return Db::newDb($this->getDbName());
        }
        if (!isset($this->db)) {
            $dbName = $this->getDbName();
            $config = \FasterPhp\CoreApp\App::getInstance()
                ->getConfig()->db->databases->$dbName;
            $this->db = new PDO(
                $config->dsn,
                $config->username,
                $config->password
            );
        }
        return $this->db;
    }
}
