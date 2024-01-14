<?php
/**
 * Base Paginator class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Sort;

/**
 * Base Paginator class.
 */
abstract class Base
{
	protected static ?int $_defaultMaxItemsPerPage = null;
	protected static ?int $_defaultMaxPageLinks = null;

	protected ?Sort $_sort;
	private ?int $_maxItemsPerPage;
	protected int $_pageNum = 1;

	private int $_numPages;
	protected int $_numItemsOnPage;
	protected int $_numItemsTotal;

	protected array $_items;

	public static function setDefaultMaxItemsPerPage(?int $defaultMaxItemsPerPage): void
	{
		self::$_defaultMaxItemsPerPage = $defaultMaxItemsPerPage;
	}

	public static function setDefaultMaxPageLinks(?int $defaultMaxPageLinks): void
	{
		self::$_defaultMaxPageLinks = $defaultMaxPageLinks;
	}

	public function __construct(Sort $sort = null)
	{
		$this->setSort($sort);
	}

	abstract public function getItems(): array;

	public function setSort(?Sort $sort): static
	{
		$this->_sort = $sort;
		unset($this->_items);
		unset($this->_numItemsOnPage);
		return $this;
	}

	public function getSort(): ?Sort
	{
		return $this->_sort;
	}

	public function setMaxItemsPerPage(?int $maxItemsPerPage): static
	{
		$this->_maxItemsPerPage = $maxItemsPerPage;
		unset($this->_items);
		unset($this->_numItemsOnPage);
		return $this;
	}

	public function getMaxItemsPerPage(): ?int
	{
		return isset($this->_maxItemsPerPage) ? $this->_maxItemsPerPage : self::$_defaultMaxItemsPerPage;
	}

	public function setMaxPageLink(?int $maxPageLinks): static
	{
		$this->_maxPageLinks = $maxPageLinks;
		return $this;
	}

	public function getMaxPageLinks(): ?int
	{
		return isset($this->_maxPageLinks) ? $this->_maxPageLinks : self::$_defaultMaxPageLinks;
	}

	public function setPageNum(int $pageNum): static
	{
		$this->_pageNum = $pageNum >= 1 ? $pageNum : 1;
		unset($this->_items);
		unset($this->_numItemsOnPage);
		return $this;
	}

	public function getPageNum(): int
	{
		return $this->_pageNum;
	}

	public function getNumPages(): int
	{
		if (!isset($this->_numPages)) {
			$maxItemsPerPage = $this->getMaxItemsPerPage();
			if (is_null($maxItemsPerPage) || 0 === $this->getNumItemsTotal()) {
				$this->_numPages = 1;
			} else {
				$this->_numPages = (int) ceil($this->getNumItemsTotal() / $maxItemsPerPage);
			}
		}
		return $this->_numPages;
	}

	public function setNumItemsOnPage(int $numItemsOnPage): static
	{
		$this->_numItemsOnPage = $numItemsOnPage;
		return $this;
	}

	public function getNumItemsOnPage(): int
	{
		if (!isset($this->_numItemsOnPage)) {
			$this->_numItemsOnPage = count($this->getItems());
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

	public function getFirstItemNum(): int
	{
		return empty($this->getNumItemsTotal()) ? 0 : (($this->getPageNum() - 1) * $this->getMaxItemsPerPage()) + 1;
	}

	public function getLastItemNum(): int
	{
		return empty($this->getNumItemsTotal()) ? 0 : $this->getFirstItemNum() + $this->getNumItemsOnPage() - 1;
	}

	public function getFirstPageLinkNum(): int
	{
		$maxPageLinks = $this->getMaxPageLinks();
		if (is_null($maxPageLinks)) {
			return 1;
		}
		$numPagesAlready = ceil($this->getPageNum() / $maxPageLinks) - 1;
		return (int) $numPagesAlready * $maxPageLinks + 1;
	}

	public function getLastPageLinkNum(): int
	{
		$maxPageLinks = $this->getMaxPageLinks();
		if (is_null($maxPageLinks)) {
			return $this->getNumPages();
		}
		return (int) min($this->getFirstPageLinkNum() + $maxPageLinks - 1, $this->getNumPages());
	}
}
