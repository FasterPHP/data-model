<?php

/**
 * Base Paginator class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Exception;
use FasterPhp\DataModel\Sort;

/**
 * Base Paginator class.
 */
abstract class Base
{
    protected static ?int $defaultMaxItemsPerPage = null;
    protected static ?int $defaultMaxPageLinks = null;
    protected ?Sort $sort;
    private ?int $maxItemsPerPage;
    private ?int $maxPageLinks;
    protected int $pageNum = 1;
    private int $numPages;
    protected int $numItemsOnPage;
    protected int $numItemsTotal;
    protected array $items;
    public static function setDefaultMaxItemsPerPage(?int $defaultMaxItemsPerPage): void
    {
        self::$defaultMaxItemsPerPage = $defaultMaxItemsPerPage;
    }

    public static function setDefaultMaxPageLinks(?int $defaultMaxPageLinks): void
    {
        self::$defaultMaxPageLinks = $defaultMaxPageLinks;
    }

    public function __construct(Sort $sort = null)
    {
        $this->setSort($sort);
    }

    abstract public function getItems(): array;
    public function setSort(?Sort $sort): static
    {
        $this->sort = $sort;
        unset($this->items);
        unset($this->numItemsOnPage);
        return $this;
    }

    public function getSort(): ?Sort
    {
        return $this->sort;
    }

    public function setMaxItemsPerPage(?int $maxItemsPerPage): static
    {
        $this->maxItemsPerPage = $maxItemsPerPage;
        unset($this->items);
        unset($this->numItemsOnPage);
        return $this;
    }

    public function getMaxItemsPerPage(): ?int
    {
        return isset($this->maxItemsPerPage) ? $this->maxItemsPerPage : self::$defaultMaxItemsPerPage;
    }

    public function setMaxPageLink(?int $maxPageLinks): static
    {
        $this->maxPageLinks = $maxPageLinks;
        return $this;
    }

    public function getMaxPageLinks(): ?int
    {
        return isset($this->maxPageLinks) ? $this->maxPageLinks : self::$defaultMaxPageLinks;
    }

    public function setPageNum(int $pageNum): static
    {
        $this->pageNum = $pageNum >= 1 ? $pageNum : 1;
        unset($this->items);
        unset($this->numItemsOnPage);
        return $this;
    }

    public function getPageNum(): int
    {
        return $this->pageNum;
    }

    public function getNumPages(): int
    {
        if (!isset($this->numPages)) {
            $maxItemsPerPage = $this->getMaxItemsPerPage();
            if (is_null($maxItemsPerPage) || 0 === $this->getNumItemsTotal()) {
                $this->numPages = 1;
            } else {
                $this->numPages = (int) ceil($this->getNumItemsTotal() / $maxItemsPerPage);
            }
        }
        return $this->numPages;
    }

    public function setNumItemsOnPage(int $numItemsOnPage): static
    {
        $this->numItemsOnPage = $numItemsOnPage;
        return $this;
    }

    public function getNumItemsOnPage(): int
    {
        if (!isset($this->numItemsOnPage)) {
            $this->numItemsOnPage = count($this->getItems());
        }
        return $this->numItemsOnPage;
    }

    public function setNumItemsTotal(int $numItemsTotal): static
    {
        $this->numItemsTotal = $numItemsTotal;
        return $this;
    }

    public function getNumItemsTotal(): int
    {
        if (!isset($this->numItemsTotal)) {
            throw new Exception('Num items total not set');
        }
        return $this->numItemsTotal;
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
