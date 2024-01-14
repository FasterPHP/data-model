<?php
/**
 * Data Model Composite Repository class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Composite;

use PDO;
use FasterPhp\Db\Db;
use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\DataModel\Sort;
use FasterPhp\DataModel\Util;

/**
 * Data Model Composite Repository class.
 */
abstract class Repository
{
	protected static string $_dbName;

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

	public function getDbName(): string
	{
		if (!isset(static::$_dbName)) {
			throw new Exception('Database name not set');
		}
		return static::$_dbName;
	}

	protected function _getData(string $sql, array $params = []): array
	{
		return $this->_paginator->setDb($this->_getDb())
			->setSql($sql)
			->setParams($params)
			->getItems(PDO::FETCH_OBJ);
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
