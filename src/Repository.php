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
	const EQUALS = 'equals';
	const STARTS = 'starts';
	const ENDS = 'ends';
	const CONTAINS = 'contains';
	const GREATER = 'greater';
	const GREATER_OR_EQUALS = 'greater or equals';
	const LESS = 'less';
	const LESS_OR_EQUALS = 'less or equals';

	const OPERATORS = [
		self::EQUALS => '=',
		self::STARTS => 'LIKE',
		self::ENDS => 'LIKE',
		self::CONTAINS => 'LIKE',
		self::GREATER => '>',
		self::GREATER_OR_EQUALS => '>=',
		self::LESS => '<',
		self::LESS_OR_EQUALS => '<=',
	];

	protected static string $_dbName;
	protected static string $_tableName;

	protected SqlPaginator $_paginator;
	protected Db|PDO $_db;
	protected string $_itemClassName;
	protected string $_setClassName;

	public function __construct(SqlPaginator|Sort $paginatorOrSort = null)
	{
		if ($paginatorOrSort instanceof SqlPaginator) {
			$this->_paginator = $paginatorOrSort;
		} elseif ($paginatorOrSort instanceof Sort) {
			$this->_paginator = new SqlPaginator($paginatorOrSort);
		} else {
			$this->_paginator = new SqlPaginator();
		}
		$this->_itemClassName = Util::getItemClassName(get_called_class());
		$this->_setClassName = Util::getSetClassName(get_called_class());
	}

	public function setSort(?Sort $sort): static
	{
		$this->_paginator->setSort($sort);
		return $this;
	}

	public function setMaxItemsPerPage(?int $maxItemsPerPage): static
	{
		$this->_paginator->setMaxItemsPerPage($maxItemsPerPage);
		return $this;
	}

	public function setDb(Db|PDO $db): static
	{
		$this->_db = $db;
		return $this;
	}

	public function getDbName(): string
	{
		if (!isset(static::$_dbName)) {
			throw new Exception('Database name not set');
		}
		return static::$_dbName;
	}

	public function getTableName(): string
	{
		if (!isset(static::$_tableName)) {
			throw new Exception('Table name not set');
		}
		return static::$_tableName;
	}

	public function getIdField(): string
	{
		if (empty($this->_itemClassName::ID_FIELD)) {
			throw new Exception('Table ID field not set');
		}
		return $this->_itemClassName::ID_FIELD;
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
		return $this->_createSetWithData($this->getDataWithParams([]));
	}

	public function getSetWithParams(array $params, array $searchTypes = []): Set
	{
		return $this->_createSetWithData($this->getDataWithParams($params, $searchTypes));
	}

	public function getDataWithParams(array $params, array $searchTypes = []): array
	{
		$sql = rtrim($this->_getSelectAndFromSql());
		[$whereSql, $whereParams] = $this->_getWhereSqlAndParams($params, $searchTypes);
		if (!empty($whereSql)) {
			$sql .= "\nWHERE " . $whereSql;
		}
		$groupBySql = $this->_getGroupBySql();
		if (!empty($groupBySql)) {
			$sql .= "\nGROUP BY " . $groupBySql;
		}
		[$havingSql, $havingParams] = $this->_getHavingSqlAndParams($params, $searchTypes);
		if (!empty($havingSql)) {
			$sql .= "\nHAVING " . $havingSql;
		}
		return $this->_getData($sql, array_merge($whereParams, $havingParams));
	}

	public function saveSet(Set $set): void
	{
		if (!$set instanceof $this->_setClassName) {
			throw new Exception("Cannot save Set of class '" . get_class($set) . "'");
		}
		$idsToDelete = [];
		foreach ($set->getRawData() as $item) {
			if (!is_object($item)) {
				continue;
			} elseif ($item->isToDelete()) {
				$idsToDelete[] = $item->getId();
			} elseif ($item->isTemp()) {
				$this->_insertItem($item);
			} elseif ($item->isDirty()) {
				$this->_updateItem($item);
			}
		}

		if (!empty($idsToDelete)) {
			$this->_deleteItemIds($idsToDelete);
		}
	}

	public function saveItem(Item $item): void
	{
		if (!$item instanceof $this->_itemClassName) {
			throw new Exception("Cannot save Item of class '" . get_class($item) . "'");
		}
		if ($item->isToDelete()) {
			$this->_deleteItemIds([$item->getId()]);
		} elseif ($item->isTemp()) {
			$this->_insertItem($item);
		} elseif ($item->isDirty()) {
			$this->_updateItem($item);
		}
	}

	protected function _createItemWithData(array $data): Item
	{
		return new $this->_itemClassName($data);
	}

	protected function _createSetWithData(array $data): Set
	{
		return new $this->_setClassName($data);
	}

	protected function _getFieldList(): string
	{
		$tableName = $this->getTableName();
		$idField = $this->getIdField();
		$fieldNames = array_keys(array_merge($this->_itemClassName::FIELDS, $this->_itemClassName::FIELDS_READONLY));

		$dbFields = array_map(function ($fieldName) use ($tableName, $idField) {
			if ($fieldName == $this->_itemClassName::ID_INTERNAL) {
				return '`' . $tableName . '`.`' . $idField . '` AS `' . $this->_itemClassName::ID_INTERNAL . '`';
			}
			return '`' . $tableName . '`.`' . $fieldName . '`';
		}, $fieldNames);

		return implode(', ', $dbFields);
	}

	protected function _getSelectAndFromSql(): string
	{
		return 'SELECT ' . $this->_getFieldList() . ' FROM `' . $this->getTableName() . '`';
	}

	protected function _getWhereSqlAndParams(array $params, array $searchTypes = []): array
	{
		return $this->_getArgsSqlAndParams(
			array_diff_key($params, $this->_itemClassName::FIELDS_AGGREGATE),
			$searchTypes
		);
	}

	protected function _getGroupBySql(): string
	{
		if (!empty($this->_itemClassName::FIELDS_AGGREGATE)) {
			return '`' . $this->getTableName() . '`.`' . $this->getIdField() . '`';
		}
		return '';
	}

	protected function _getHavingSqlAndParams(array $params, array $searchTypes = []): array
	{
		return $this->_getArgsSqlAndParams(
			array_intersect_key($params, $this->_itemClassName::FIELDS_AGGREGATE),
			$searchTypes
		);
	}

	protected function _getArgsSqlAndParams(array $params, array $searchTypes = []): array
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
			} elseif (isset($this->_itemClassName::FIELDS[$key])
				|| isset($this->_itemClassName::FIELDS_READONLY[$key])
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
				$quotedValues = implode(', ', array_map([$this->_getDb(), 'quote'], $nonNullValues));
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

					case self::CONTAINS;
						$value = '%' . $value . '%';
						break;

					default;
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

	protected function _getData(string $sql, array $params = []): array
	{
		return $this->_paginator
			->setDb($this->_getDb())
			->setSql($sql)
			->setParams($params)
			->getItems()
		;
	}

	protected function _insertItem(Item $item): void
	{
		$sqlValues = $item->getSqlValues(false);

		$placeholders = [];
		$params = [];
		foreach ($sqlValues as $fieldName => $sqlValue) {
			if ($fieldName == $this->_itemClassName::ID_INTERNAL) {
				if (is_null($sqlValue)) {
					continue;
				}
				$fieldName = $this->_itemClassName::ID_FIELD;
			}
			$placeholders[] = '`' . $fieldName . '` = :' . $fieldName;
			$params[':' . $fieldName] = $sqlValue;
		}

		$sql = 'INSERT INTO `' . $this->getTableName() . '`'
			. ' SET '. implode(', ', $placeholders);

//		echo "<pre>";
//		echo "$sql\n";
//		echo "\$params: " . var_export($params, true);
//		exit;

		$db = $this->_getDb();
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

	protected function _updateItem(Item $item): void
	{
		$sqlValues = $item->getChangedSqlValues();

		$placeholders = [];
		$params = [':id' => $item->getId()];
		foreach ($sqlValues as $fieldName => $sqlValue) {
			$placeholders[] = '`' . $fieldName . '` = :' . $fieldName;
			$params[':' . $fieldName] = $sqlValue;
		}

		$sql = 'UPDATE `' . $this->getTableName(). '`'
			. ' SET '. implode(', ', $placeholders)
			. ' WHERE `' . $this->getIdField() . '` = :id';
		$stmt = $this->_getDb()->prepare($sql);
		$stmt->execute($params);

		$item->clearOriginalValues();
	}

	protected function _deleteItemIds(array $itemIds): void
	{
		$db = $this->_getDb();
		$quotedIds = implode(', ', array_map([$db, 'quote'], $itemIds));
		$sql = 'DELETE FROM `' . $this->getTableName() . '`'
			. ' WHERE `' . $this->getIdField() . '`'
			. ' IN (' . $quotedIds . ')';
		$db->exec($sql);
	}

	protected function _getDb(): Db|PDO
	{
		if (!isset($this->_db) && class_exists('\FasterPhp\Db\Db')) {
			return Db::newDb($this->getDbName());
		}

		if (!isset($this->_db)) {
			$dbName = $this->getDbName();
			$config = \FasterPhp\CoreApp\App::getInstance()->getConfig()->db->databases->$dbName;
			$this->_db = new \PDO($config->dsn, $config->username, $config->password);
		}

		return $this->_db;
	}
}
