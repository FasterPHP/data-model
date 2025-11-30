<?php

/**
 * SQL Paginator class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Exception;
use FasterPhp\DataModel\Sort;
use FasterPhp\DataModel\Sql;
use PDO;

/**
 * SQL Paginator class.
 */
class SqlPaginator extends Base
{
    protected PDO $pdo;
    protected string $sql;
    protected array $params;
    protected array $sortFields = [];

    public function __construct(PDO $pdo, ?Sort $sort = null)
    {
        $this->pdo = $pdo;
        parent::__construct($sort);
    }

    public function setPdo(PDO $pdo): static
    {
        if (isset($this->pdo) && $pdo !== $this->pdo) {
            $this->clearResults();
        }
        $this->pdo = $pdo;
        return $this;
    }

    public function setSql(string $sql): static
    {
        if (isset($this->sql) && $sql != $this->sql) {
            $this->clearResults();
        }
        $this->sql = $sql;
        return $this;
    }

    public function setParams(array $params): static
    {
        if (isset($this->params) && $params != $this->params) {
            $this->clearResults();
        }
        $this->params = $params;
        return $this;
    }

    public function setSort(?Sort $sort): static
    {
        parent::setSort($sort);

        $this->sortFields = [];
        if (!is_null($sort)) {
            $this->addSort($sort);
        }

        return $this;
    }

    public function addSort(Sort $sort): void
    {
        $direction = $sort->getSortDirection() === Sort::DESCENDING ? 'DESC' : 'ASC';
        $this->sortFields[$sort->getSortField()] = $direction;

        // If sort contains secondary sort, recurse
        $secondarySort = $sort->getSecondarySort();
        if (!empty($secondarySort)) {
            $this->addSort($secondarySort);
        }

        $this->clearResults();
    }

    public function getItems(int $mode = PDO::FETCH_ASSOC): array
    {
        if (!isset($this->items)) {
//            echo "\nSQL: " . $this->getPaginatedSql() . "\n";
//            echo "\$params: " . print_r($this->getParams(), true) . "\n";

            $stmt = $this->getPdo()->prepare($this->getPaginatedSql());
            $stmt->execute($this->getParams());

            $this->items = $stmt->fetchAll($mode) ?? [];
        }
        return $this->items;
    }

    public function getNumItemsTotal(): int
    {
        if (!isset($this->numItemsTotal)) {
            $stmt = $this->getPdo()->prepare("SELECT COUNT(*) FROM ({$this->getSql()}) AS numItemsTotal");
            $stmt->execute($this->getParams());
            $this->setNumItemsTotal((int) $stmt->fetchColumn());
        }
        return $this->numItemsTotal;
    }

    public function getPaginatedSql(): string
    {
        $sql = $this->getSql();

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
        if (!empty($this->sortFields)) {
            foreach ($this->sortFields as $field => $direction) {
                $sort .= ', ' . Sql::ident($field) . ' ' . $direction;
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
            if ($this->pageNum > 1) {
                $sql .= ' OFFSET ' . (($this->pageNum - 1) * $maxItemsPerPage);
            }
        }
        return $sql;
    }

    protected function getPdo(): PDO
    {
        return $this->pdo;
    }

    protected function getSql(): string
    {
        if (empty($this->sql)) {
            throw new Exception('SQL not set');
        }
        return $this->sql;
    }

    protected function getParams(): array
    {
        if (!isset($this->params)) {
            throw new Exception('Params not set');
        }
        return $this->params;
    }

    protected function clearResults()
    {
        unset($this->items);
        unset($this->numItemsOnPage);
        unset($this->numItemsTotal);
    }
}
