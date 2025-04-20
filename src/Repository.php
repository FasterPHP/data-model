<?php

/**
 * Data Model Repository class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PDO;
use FasterPhp\Db\Db;
use FasterPhp\DataModel\Paginator\SqlPaginator;

/**
 * Data Model Repository class.
 */
abstract class Repository
{
    protected const EQUALS = 'equals';
    protected const NOT_EQUALS = 'not equals';
    protected const STARTS = 'starts';
    protected const ENDS = 'ends';
    protected const CONTAINS = 'contains';
    protected const GREATER = 'greater';
    protected const GREATER_OR_EQUALS = 'greater or equals';
    protected const LESS = 'less';
    protected const LESS_OR_EQUALS = 'less or equals';
    protected const OPERATORS = [
        self::EQUALS => '=',
        self::NOT_EQUALS => '!=',
        self::STARTS => 'LIKE',
        self::ENDS => 'LIKE',
        self::CONTAINS => 'LIKE',
        self::GREATER => '>',
        self::GREATER_OR_EQUALS => '>=',
        self::LESS => '<',
        self::LESS_OR_EQUALS => '<=',
    ];
    protected static string $dbName;
    protected static string $tableName;
    protected SqlPaginator $paginator;
    protected Db|PDO $db;
    protected string $itemClassName;
    protected string $setClassName;

    public function __construct(SqlPaginator|Sort $paginatorOrSort = null)
    {
        if ($paginatorOrSort instanceof SqlPaginator) {
            $this->paginator = $paginatorOrSort;
        } elseif ($paginatorOrSort instanceof Sort) {
            $this->paginator = new SqlPaginator($paginatorOrSort);
        } else {
            $this->paginator = new SqlPaginator();
        }
        $this->itemClassName = Util::getItemClassName(get_called_class());
        $this->setClassName = Util::getSetClassName(get_called_class());
    }

    public function setSort(?Sort $sort): static
    {
        $this->paginator->setSort($sort);
        return $this;
    }

    public function setMaxItemsPerPage(?int $maxItemsPerPage): static
    {
        $this->paginator->setMaxItemsPerPage($maxItemsPerPage);
        return $this;
    }

    public function setDb(Db|PDO $db): static
    {
        $this->db = $db;
        return $this;
    }

    public function getDbName(): string
    {
        if (!isset(static::$dbName)) {
            throw new Exception('Database name not set');
        }
        return static::$dbName;
    }

    public function getTableName(): string
    {
        if (!isset(static::$tableName)) {
            throw new Exception('Table name not set');
        }
        return static::$tableName;
    }

    public function getIdField(): string
    {
        if (empty($this->itemClassName::ID_FIELD)) {
            throw new Exception('Table ID field not set');
        }
        return $this->itemClassName::ID_FIELD;
    }

    public function getItemWithId(mixed $id): ?Item
    {
        $set = $this->getSetWithParams([$this->getTableName() . '.' . $this->getIdField() => $id]);
        if (0 === count($set)) {
            return null;
        }
        return $set[0];
    }

    public function getItemWithParams(array $params, array $searchTypes = []): ?Item
    {
        $set = $this->getSetWithParams($params, $searchTypes);
        if (0 === count($set)) {
            return null;
        }
        return $set[0];
    }

    public function getSetOfAll(): Set
    {
        return $this->createSetWithData($this->getDataWithParams([]));
    }

    public function getSetWithParams(array $params, array $searchTypes = []): Set
    {
        return $this->createSetWithData($this->getDataWithParams($params, $searchTypes));
    }

    public function getDataWithParams(array $params, array $searchTypes = []): array
    {
        $sql = rtrim($this->getSelectAndFromSql());
        [$whereSql, $whereParams] = $this->getWhereSqlAndParams($params, $searchTypes);
        if (!empty($whereSql)) {
            $sql .= "\nWHERE " . $whereSql;
        }
        $groupBySql = $this->getGroupBySql();
        if (!empty($groupBySql)) {
            $sql .= "\nGROUP BY " . $groupBySql;
        }
        [$havingSql, $havingParams] = $this->getHavingSqlAndParams($params, $searchTypes);
        if (!empty($havingSql)) {
            $sql .= "\nHAVING " . $havingSql;
        }
        return $this->getData($sql, array_merge($whereParams, $havingParams));
    }

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

    protected function createItemWithData(array $data): Item
    {
        return new $this->itemClassName($data);
    }

    protected function createSetWithData(array $data): Set
    {
        return new $this->setClassName($data);
    }

    protected function getFieldList(): string
    {
        $tableName = $this->getTableName();
        $idField = $this->getIdField();
        $fieldNames = array_keys(array_merge($this->itemClassName::FIELDS, $this->itemClassName::FIELDS_READONLY));
        $dbFields = array_map(function ($fieldName) use ($tableName, $idField) {

            if ($fieldName == $this->itemClassName::ID_INTERNAL) {
                return '`' . $tableName . '`.`' . $idField . '` AS `' . $this->itemClassName::ID_INTERNAL . '`';
            }
            return '`' . $tableName . '`.`' . $fieldName . '`';
        }, $fieldNames);
        return implode(', ', $dbFields);
    }

    protected function getSelectAndFromSql(): string
    {
        return 'SELECT ' . $this->getFieldList() . ' FROM `' . $this->getTableName() . '`';
    }

    protected function getWhereSqlAndParams(array $params, array $searchTypes = []): array
    {
        return $this->getArgsSqlAndParams(
            array_diff_key($params, $this->itemClassName::FIELDS_AGGREGATE),
            $searchTypes
        );
    }

    protected function getGroupBySql(): string
    {
        if (!empty($this->itemClassName::FIELDS_AGGREGATE)) {
            return '`' . $this->getTableName() . '`.`' . $this->getIdField() . '`';
        }
        return '';
    }

    protected function getHavingSqlAndParams(array $params, array $searchTypes = []): array
    {
        return $this->getArgsSqlAndParams(
            array_intersect_key($params, $this->itemClassName::FIELDS_AGGREGATE),
            $searchTypes
        );
    }

    protected function getArgsSqlAndParams(array $params, array $searchTypes = []): array
    {
        $tableName = $this->getTableName();
        $argsSql = '';
        $args = [];
        foreach ($params as $key => $value) {
            // Set search type and ensure operator defined
            if (!array_key_exists($key, $searchTypes)) {
                $searchType = self::EQUALS;
            } elseif (!array_key_exists($searchTypes[$key], self::OPERATORS)) {
                throw new Exception("Unsupported search type '{$searchTypes[$key]}'");
            } else {
                $searchType = $searchTypes[$key];
            }

            // Create safe and unambiguous placeholder
            $placeholder = ':' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key);

            // Create safe version of key name (field name, plus table name where needed)
            if (false !== strpos($key, '.')) {
                // If table and field included, escape with backticks
                $safeKey = '`' . str_replace('.', '`.`', $key) . '`';
            } elseif (
                isset($this->itemClassName::FIELDS[$key])
                || isset($this->itemClassName::FIELDS_READONLY[$key])
            ) {
                // If field in primary table, include table name to avoid ambiguity
                $safeKey = '`' . $tableName . '`.`' . $key . '`';
            } else {
                $safeKey = '`' . $key . '`';
            }

            // If value is array of one, just extract value and treat as scalar
            $hasNullValue = false;
            if (is_array($value)) {
                $value = array_unique($value);
                if (1 === count($value)) {
                    $value = array_pop($value);
                } elseif (2 === count($value) && in_array(null, $value)) {
                    $hasNullValue = true;
                    $value = array_filter($value, function ($thisValue) {
                        return null !== $thisValue;
                    });
                    $value = array_pop($value);
                }
            }

            // Convert array of values to IN statement
            if ($searchType == self::EQUALS && is_array($value)) {
                $nonNullValues = [];
                foreach ($value as $thisValue) {
                    if (null === $thisValue) {
                        $hasNullValue = true;
                    } else {
                        $nonNullValues[] = $thisValue;
                    }
                }
                $quotedValues = implode(', ', array_map([$this->getDb(), 'quote'], $nonNullValues));
                $argsSql .= ' AND (' . $safeKey . ' IN (' . $quotedValues . ')';
                if ($hasNullValue) {
                    $argsSql .= ' OR ' . $safeKey . ' IS NULL';
                }
                $argsSql .= ')';

            // Deal with null values
            } elseif ($searchType == self::EQUALS && is_null($value)) {
                $argsSql .= ' AND ' . $safeKey . ' IS NULL';

            // Scalar values with/without wildcards
            } else {
                $thisArgSql = $safeKey . ' ' . self::OPERATORS[$searchType] . ' ' . $placeholder;
                switch ($searchType) {
                    case self::STARTS:
                        $value = $value . '%';
                        break;
                    case self::ENDS:
                        $value = '%' . $value;
                        break;
                    case self::CONTAINS:
                        $value = '%' . $value . '%';
                        break;
                    default:
                        break;
                }
                if ($hasNullValue) {
                    $argsSql .= ' AND (' . $thisArgSql . ' OR ' . $safeKey . ' IS NULL)';
                } else {
                    $argsSql .= ' AND ' . $thisArgSql;
                }

                $args[$placeholder] = $value;
            }
        }
        return [substr($argsSql, 5), $args];
    }

    protected function getData(string $sql, array $params = []): array
    {
        return $this->paginator
            ->setDb($this->getDb())
            ->setSql($sql)
            ->setParams($params)
            ->getItems()
        ;
    }

    protected function insertItem(Item $item): void
    {
        $sqlValues = $item->getSqlValues(false);
        $placeholders = [];
        $params = [];
        foreach ($sqlValues as $fieldName => $sqlValue) {
            if ($fieldName == $this->itemClassName::ID_INTERNAL) {
                if (is_null($sqlValue)) {
                    continue;
                }
                $fieldName = $this->itemClassName::ID_FIELD;
            }
            $placeholders[] = '`' . $fieldName . '` = :' . $fieldName;
            $params[':' . $fieldName] = $sqlValue;
        }

        $sql = 'INSERT INTO `' . $this->getTableName() . '`'
            . ' SET ' . implode(', ', $placeholders);
//      echo "<pre>";
//        echo "$sql\n";
//       echo "\$params: " . var_export($params, true);
//       exit;

        $db = $this->getDb();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if (empty($item->getId())) {
        //$newId = $db->lastInsertId();
            $idStmt = $db->query("SELECT MAX(`{$this->getIdField()}`) FROM `{$this->getTableName()}`");
            if ($idStmt) {
                $newId = $idStmt->fetchColumn();
                if (!empty($newId)) {
                    $item->setId($newId);
                }
            }
        }
        $item->clearOriginalValues();
    }

    protected function updateItem(Item $item): void
    {
        $sqlValues = $item->getChangedSqlValues();
        $placeholders = [];
        $params = [':id' => $item->getId()];
        foreach ($sqlValues as $fieldName => $sqlValue) {
            $placeholders[] = '`' . $fieldName . '` = :' . $fieldName;
            $params[':' . $fieldName] = $sqlValue;
        }

        $sql = 'UPDATE `' . $this->getTableName() . '`'
            . ' SET ' . implode(', ', $placeholders)
            . ' WHERE `' . $this->getIdField() . '` = :id';
        $stmt = $this->getDb()->prepare($sql);
        $stmt->execute($params);
        $item->clearOriginalValues();
    }

    protected function deleteItemIds(array $itemIds): void
    {
        $db = $this->getDb();
        $quotedIds = implode(', ', array_map([$db, 'quote'], $itemIds));
        $sql = 'DELETE FROM `' . $this->getTableName() . '`'
            . ' WHERE `' . $this->getIdField() . '`'
            . ' IN (' . $quotedIds . ')';
        $db->exec($sql);
    }

    protected function getDb(): Db|PDO
    {
        if (!isset($this->db) && class_exists('\FasterPhp\Db\Db')) {
            return Db::newDb($this->getDbName());
        }

        if (!isset($this->db)) {
            $dbName = $this->getDbName();
            $config = \FasterPhp\CoreApp\App::getInstance()->getConfig()->db->databases->$dbName;
            $this->db = new \PDO($config->dsn, $config->username, $config->password);
        }

        return $this->db;
    }
}
