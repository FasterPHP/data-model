<?php
/**
 * Base Paginator class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use OutOfBoundsException;
use FasterPhp\DataModel\Sort;

/**
 * Base Paginator class.
 */
abstract class Base
{
	protected ?Sort $_sort;
	protected ?int $_maxItemsPerPage = null;
	protected int $_pageNum = 1;

	protected int $_numPages;
	protected int $_numItemsOnPage;
	protected int $_numItemsTotal;

	public function __construct(Sort $sort = null)
	{
		$this->setSort($sort);
	}

	public function setSort(?Sort $sort): static
	{
		$this->_sort = $sort;
		return $this;
	}

	public function getSort(): ?Sort
	{
		return $this->_sort;
	}

	public function setMaxItemsPerPage(?int $maxItemsPerPage): static
	{
		$this->_maxItemsPerPage = $maxItemsPerPage;
		return $this;
	}

	public function getMaxItemsPerPage(): ?int
	{
		return $this->_maxItemsPerPage;
	}

	public function setPageNum(int $pageNum): static
	{
		$this->_pageNum = $pageNum;
		return $this;
	}

	public function getPageNum(): int
	{
		return $this->_pageNum;
	}

	public function getNumPages(): int
	{
		return ceil($this->getNumItemsTotal() / $this->_maxItemsPerPage);
	}

	public function setNumItemsOnPage(int $numItemsOnPage): static
	{
		$this->_numItemsOnPage = $numItemsOnPage;
		return $this;
	}

	public function getNumItemsOnPage(): int
	{
		if (!isset($this->_numItemsOnPage)) {
			throw new Exception('Num items on page not set');
		}
		return $this->_numItemsOnPage;
	}

	public function setNumItemsTotal(int $numItemsTotal): static
	{
		$this->_numItemsTotal = $numItemsTotal;
		return $this;
	}

	public function getNumItemsTotal(): int
	{
		if (!isset($this->_numItemsTotal)) {
			throw new Exception('Num items total not set');
		}
		return $this->_numItemsTotal;
	}
}
