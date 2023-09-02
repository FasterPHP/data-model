<?php
/**
 * SQL Paginator class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Sort;

/**
 * SQL Paginator class.
 */
class SqlPaginator extends Base
{
	protected array $_sortFields = [];

	public function setSort(?Sort $sort): static
	{
		parent::setSort($sort);

		$this->_sortFields = [];
		if (!is_null($sort)) {
			$this->addSort($sort);
		}

		return $this;
	}

	public function addSort(Sort $sort): void
	{
		$direction = $sort->getSortDirection() === Sort::DESCENDING ? 'DESC' : 'ASC';
		$this->_sortFields[$sort->getSortField()] = $direction;

		// If sort contains secondary sort, recurse
		$secondarySort = $sort->getSecondarySort();
		if (!empty($secondarySort)) {
			$this->addSort($secondarySort);
		}
	}

	/**
	 * Returns the total number of rows in the result set.
	 *
	 * @return integer
	 */
	public function count(): int
	{
		if (!isset($this->_count)) {
			$sql = "SELECT COUNT(*) FROM ({$this->_sql}) AS totalRowsWithoutPagination";

			$this->_count = (int) DbManager::newDb($this->_dbName, false)->fetchOne($sql, $this->_args);
		}
		return $this->_count;
	}

	/**
	 * Returns a collection of items for a page.
	 *
	 * @param integer $offset           Page offset
	 * @param integer $itemCountPerPage Number of items per page
	 *
	 * @return array
	 */
	public function getItems($offset, $itemCountPerPage): array
	{
		$sql = $this->_sql . $this->_getSortSql();
		if ($itemCountPerPage > 0) {
			$sql .= $this->_getLimitSql($offset, $itemCountPerPage);
		}

		return DbManager::newDb($this->_dbName, false)->fetchAll($sql, $this->_args);
	}

	/**
	 * Get ORDER BY clause SQL.
	 *
	 * @return string
	 */
	public function getSortSql(): string
	{
		$sort = '';
		if (!empty($this->_sortFields)) {
			foreach ($this->_sortFields as $field => $direction) {
				$sort .= ', `' . str_replace('.', '`.`', $field) . '` ' . $direction;
			}
			$sort = ' ORDER BY ' . substr($sort, 2);
		}
		return $sort;
	}

	/**
	 * Get LIMIT clause SQL.
	 *
	 * @return string
	 */
	public function getLimitSql(): string
	{
		$sql = '';
		if (isset($this->_maxItemsPerPage)) {
			$sql .= ' LIMIT ' . $this->_maxItemsPerPage;
			if ($this->_pageNum > 1) {
				$sql .= ' OFFSET ' . (($this->_pageNum - 1) * $this->_maxItemsPerPage);
			}
		}
		return $sql;
	}
}
