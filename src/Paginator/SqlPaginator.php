<?php
/**
 * SQL Paginator class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Exception;
use FasterPhp\DataModel\Sort;
use FasterPhp\Db\Db;
use PDO;

/**
 * SQL Paginator class.
 */
class SqlPaginator extends Base
{
	protected Db|PDO $_db;
	protected string $_sql;
	protected array $_params;
	protected array $_sortFields = [];

	public function setDb(Db|PDO $db): static
	{
		if (isset($this->_db) && $db !== $this->_db) {
			$this->_clearResults();
		}
		$this->_db = $db;
		return $this;
	}

	public function setSql(string $sql): static
	{
		if (isset($this->_sql) && $sql != $this->_sql) {
			$this->_clearResults();
		}
		$this->_sql = $sql;
		return $this;
	}

	public function setParams(array $params): static
	{
		if (isset($this->_params) && $params != $this->_params) {
			$this->_clearResults();
		}
		$this->_params = $params;
		return $this;
	}

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

		$this->_clearResults();
	}

	public function getItems(int $mode = PDO::FETCH_ASSOC): array
	{
		if (!isset($this->_items)) {
//			echo "<pre>";
//			echo "\nSQL: " . $this->getPaginatedSql() . "\n";
//			echo "\$params: " . print_r($this->_getParams(), true) . "\n";
//			echo "</pre>\n";
//			exit;

			$stmt = $this->_getDb()->prepare($this->getPaginatedSql());
			$stmt->execute($this->_getParams());

			$this->_items = $stmt->fetchAll($mode) ?? [];
		}
		return $this->_items;
	}

	public function getNumItemsTotal(): int
	{
		if (!isset($this->_numItemsTotal)) {
			$stmt = $this->_getDb()->prepare("SELECT COUNT(*) FROM ({$this->_getSql()}) AS numItemsTotal");
			$stmt->execute($this->_getParams());
			$this->setNumItemsTotal((int) $stmt->fetchColumn());
		}
		return $this->_numItemsTotal;
	}

	public function getPaginatedSql(): string
	{
		$sql = $this->_getSql();

		$sortSql = $this->getSortSql();
		if (!empty($sortSql)) {
			$sql .= ' ' . $sortSql;
		}

		$limitSql = $this->getLimitSql();
		if (!empty($limitSql)) {
			$sql .= ' ' . $limitSql;
		}

		return $sql;
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
			$sort = 'ORDER BY ' . substr($sort, 2);
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
		$maxItemsPerPage = $this->getMaxItemsPerPage();
		if (!is_null($maxItemsPerPage)) {
			$sql .= 'LIMIT ' . $maxItemsPerPage;
			if ($this->_pageNum > 1) {
				$sql .= ' OFFSET ' . (($this->_pageNum - 1) * $maxItemsPerPage);
			}
		}
		return $sql;
	}

	protected function _getDb(): Db|PDO
	{
		if (!isset($this->_db)) {
			throw new Exception('Db not set');
		}
		return $this->_db;
	}

	protected function _getSql(): string
	{
		if (empty($this->_sql)) {
			throw new Exception('SQL not set');
		}
		return $this->_sql;
	}

	protected function _getParams(): array
	{
		if (!isset($this->_params)) {
			throw new Exception('Params not set');
		}
		return $this->_params;
	}

	protected function _clearResults()
	{
		unset($this->_items);
		unset($this->_numItemsOnPage);
		unset($this->_numItemsTotal);
	}
}
