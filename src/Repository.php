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
		$sql = $this->_getDefaultSelectAndFromSql() . ' WHERE `' . $this->getIdField() . '` = :id';

		return $this->_getItem($sql, [':id' => $id]);
	}

	public function getItemWithParams(array $data, array $searchTypes = []): ?Item
	{
		$set = $this->getSetWithParams($data, $searchTypes);
		if (0 === count($set)) {
			return null;
		}
		return $set[0];
	}

	public function getSetOfAll(): Set
	{
		return $this->getSetWithData($this->getData($this->_getDefaultSelectAndFromSql()));
	}

	public function getSetWithParams(array $data, array $searchTypes = []): Set
	{
		return $this->getSetWithData($this->getDataWithParams($data, $searchTypes));
	}

	public function getSetWithData(array $data): Set
	{
		return new $this->_setClassName($data);
	}

	public function getDataWithParams(array $data, array $searchTypes = [], $selectAndFromSql = null): array
	{
		$sql = $selectAndFromSql ?? $this->_getDefaultSelectAndFromSql();
		[$whereSql, $params] = $this->_getWhereSqlAndParams($data, $searchTypes);
		if (!empty($whereSql)) {
			$sql .= ' WHERE ' . $whereSql;
		}
		return $this->getData($sql, $params);
	}

	public function getData(string $sql, array $params = []): array
	{
		return $this->_paginator->setDb($this->_getDb())
			->setSql($sql)
			->setParams($params)
			->getItems();
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

	protected function _getFieldList(): string
	{
		$idField = $this->getIdField();
		$dbFields = array_map(function ($fieldName) use ($idField) {
			if ($fieldName == $this->_itemClassName::ID_INTERNAL) {
				return '`' . $idField . '` AS `' . $this->_itemClassName::ID_INTERNAL . '`';
			}
			return '`' . $fieldName . '`';
		}, array_keys($this->_itemClassName::FIELDS));

		return implode(', ', $dbFields);
	}

	protected function _getDefaultSelectAndFromSql(): string
	{
		return 'SELECT ' . $this->_getFieldList() . ' FROM `' . $this->getTableName() . '`';
	}

	protected function _getWhereSqlAndParams(array $data, array $searchTypes = []): array
	{
		$whereSql = '';
		$params = [];
		foreach ($data as $key => $value) {
			if (!array_key_exists($key, $searchTypes)) {
				$searchType = self::EQUALS;
			} elseif (!array_key_exists($searchTypes[$key], self::OPERATORS)) {
				throw new Exception("Unsupported search type '{$searchTypes[$key]}'");
			} else {
				$searchType = $searchTypes[$key];
			}
			$placeholder = ':' . preg_replace('/^[^\.]+\./', '', $key);
			$safeKey = '`' . str_replace('.', '`.`', $key) . '`';
			$whereSql .= ' AND ' . $safeKey . ' ' . self::OPERATORS[$searchType] . ' ' . $placeholder;

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

			$params[$placeholder] = $value;
		}
		return [substr($whereSql, 5), $params];
	}

	protected function _getItem(string $sql, array $params): ?Item
	{
		$stmt = $this->_getDb()->prepare($sql);
		$stmt->execute($params);

		$data = $stmt->fetch(PDO::FETCH_ASSOC);
		if (empty($data)) {
			return null;
		}

		return new $this->_itemClassName($data);
	}

	protected function _insertItem(Item $item): void
	{
		$sqlValues = $item->getSqlValues();

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

		$db = $this->_getDb();
		$stmt = $db->prepare($sql);
		$stmt->execute($params);

		$item->setId($db->lastInsertId());
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
		if (isset($this->_db)) {
			return $this->_db;
		} elseif (class_exists('\FasterPhp\Db\Db')) {
			return Db::newDb($this->getDbName());
		}
		throw new Exception('Database connection not set and \\FasterPhp\\Db\\Db not available');
	}
}
