<?php

/**
 * Sort class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Sort class.
 */
class Sort
{
    public const ASCENDING = 'asc';
    public const DESCENDING = 'desc';

    /**
     * The field to sort on.
     *
     * @var string
     */
    protected string $sortField;

    /**
     * The sort direction.
     *
     * @var string
     */
    protected string $sortDirection;

    /**
     * An optional secondary sort to apply if the sort returns equivalence.
     *
     * @var Sort|null
     */
    protected ?Sort $secondarySort = null;

    /**
     * Get whether sort direction is valid.
     *
     * @param string|null $direction The direction of the sort.
     *
     * @return boolean
     */
    public static function isValidSortDirection(?string $direction): bool
    {
        return is_null($direction) || in_array($direction, [Sort::ASCENDING, Sort::DESCENDING]);
    }

    /**
     * Constructor.
     *
     * @param string  $field         The sort field.
     * @param string  $direction     The sort direction.
     * @param ?Sort   $secondarySort An optional secondary sort.
     */
    public function __construct($field, $direction = self::ASCENDING, Sort $secondarySort = null)
    {
        $this->setSortField($field);
        $this->setSortDirection($direction);
        $this->setSecondarySort($secondarySort);
    }

    /**
     * Return the current sort field.
     *
     * @return string The name of the sort field.
     */
    public function getSortField(): string
    {
        return $this->sortField;
    }

    /**
     * Set the current sort field.
     *
     * @param string $field The name of the sort field.
     *
     * @return static
     */
    public function setSortField(string $field): static
    {
        $this->sortField = $field;
        return $this;
    }

    /**
     * Return the current sort direction.
     *
     * @return string The direction of the sort.
     */
    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    /**
     * Set the current sort direction.
     *
     * @param string|null $direction The direction of the sort.
     *
     * @return static
     */

    public function setSortDirection(?string $direction): static
    {
        if (false === self::isValidSortDirection($direction)) {
            throw new Exception("Invalid sort direction '$direction'");
        }
        $this->sortDirection = $direction;
        return $this;
    }

    /**
     * Set a secondary sort to apply if two elements are equivalent.
     *
     * If, when compared with the sort, 2 elements come back as '0'
     * (equivalent) then the secondary sort will be applied. This secondary
     * sort can, itself, have a secondary sort so creating a sort chain.
     *
     * @param Sort $sort The secondary sort.
     *
     * @return static
     */
    public function setSecondarySort(?Sort $sort): static
    {
        $this->secondarySort = $sort;
        return $this;
    }

    /**
     * Return secondary sort, if available.
     *
     * @return Sort|null
     */
    public function getSecondarySort(): ?Sort
    {
        return $this->secondarySort;
    }
}
