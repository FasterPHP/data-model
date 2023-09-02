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
		$sql = 'SELECT ' . $this->_getFieldList() . ' FROM `' . $this->getTableName() . '` WHERE `' . $this->getIdField() . '` = :id';

		return $this->_getItem($sql, [':id' => $id]);
	}

	public function getSetOfAll(): Set
	{
		return $this->_getSet('SELECT ' . $this->_getFieldList() . ' FROM `' . $this->getTableName() . '`');
	}

	public function getSetWithData(array $data): Set
	{
		if (empty($data)) {
			throw new Exception('Data array cannot be empty');
		}

		$whereSql = '';
		$params = [];
		foreach ($data as $key => $value) {
			$whereSql .= " AND `$key` = :$key";
			$params[':' . $key] = $value;
		}
		$whereSql = substr($whereSql, 5);

		$sql = 'SELECT ' . $this->_getFieldList() . ' FROM `' . $this->getTableName() . '` WHERE ' . $whereSql;

		return $this->_getSet($sql, $params);
	}

	public function saveSet(Set $set): void
	{
		if (!$set instanceof $this->_setClassName) {
			throw new Exception("Cannot save Set of class '" . get_class($set) . "'");
		}
		$idsToDelete = [];
		foreach ($set->getRawData() as $position => $item) {
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

	protected function _insertItem(Item $item): void
	{
		$sqlValues = $item->getSqlValues();

		$placeholders = [];
		$params = [];
		foreach ($sqlValues as $fieldName => $sqlValue) {
			if ($fieldName == $this->_itemClassName::ID_INTERNAL) {
				continue;
			}
			$placeholders[] = '`' . $fieldName . '` = :' . $fieldName;
			$params[':' . $fieldName] = $sqlValue;
		}

		$sql = 'INSERT INTO `' . $this->getTableName() . '` SET '. implode(', ', $placeholders);

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

		$sql = 'UPDATE `' . $this->getTableName() . '` SET '. implode(', ', $placeholders) .  ' WHERE `' . $this->getIdField() . '` = :id';
		$stmt = $this->_getDb()->prepare($sql);
		$stmt->execute($params);

		$item->clearOriginalValues();
	}

	protected function _deleteItemIds(array $itemIds): void
	{
		$db = $this->_getDb();
		$quotedIds = implode(', ', array_map([$db, 'quote'], $itemIds));
		$sql = 'DELETE FROM `' . $this->getTableName() . '` WHERE `' . $this->getIdField() . '` IN (' . $quotedIds . ')';
		$db->exec($sql);
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

	protected function _getSet(string $sql, array $params = []): Set
	{
		$db = $this->_getDb();

		$maxItemsPerPage = $this->_paginator->getMaxItemsPerPage();
		if (null !== $maxItemsPerPage) {
			$stmt = $db->prepare("SELECT COUNT(*) FROM ($sql) AS numItemsTotal");
			$stmt->execute($params);
			$this->_paginator->setNumItemsTotal((int) $stmt->fetchColumn());
		}

		$sql .= $this->_paginator->getSortSql() ?: '';
		$sql .= $this->_paginator->getLimitSql() ?: '';

		$stmt = $db->prepare($sql);
		$stmt->execute($params);

		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$numItems = count($data);
		$this->_paginator->setNumItemsOnPage($numItems);
		if (null === $maxItemsPerPage) {
			$this->_paginator->setNumItemsTotal($numItems);
		}

		return new $this->_setClassName($data);
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

	protected function _getDb(): Db|PDO
	{
		if (isset($this->_db)) {
			return $this->_db;
		} elseif (class_exists('Db')) {
			return Db::newDb($this->getDbName());
		}
		throw new Exception('Database connection not set and \\FasterPhp\\Db\\Db not available');
	}
}
