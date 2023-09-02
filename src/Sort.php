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
	const ASCENDING = 1;
	const DESCENDING = 0;

	/**
	 * The field to sort on.
	 *
	 * @var string
	 */
	protected string $_sortField;

	/**
	 * The sort direction.
	 *
	 * @var integer
	 */
	protected int $_sortDirection;

	/**
	 * An optional secondary sort to apply if the sort returns equivilance.
	 *
	 * @var Sort|null
	 */
	protected ?Sort $_secondarySort = null;

	/**
	 * Constructor.
	 *
	 * @param string  $field         The sort field.
	 * @param integer $direction     The sort direction.
	 * @param ?Sort   $secondarySort An optional secondary sort.
	 */
	public function __construct(
		$field,
		$direction = self::ASCENDING,
		Sort $secondarySort = null
	) {
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
		return $this->_sortField;
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
		$this->_sortField = $field;
		return $this;
	}

	/**
	 * Return the current sort direction.
	 *
	 * @return integer The direction of the sort.
	 */
	public function getSortDirection(): int
	{
		return $this->_sortDirection;
	}

	/**
	 * Set the current sort direction.
	 *
	 * @param integer|null $direction The direction of the sort.
	 *
	 * @return static
	 */

	public function setSortDirection(?int $direction): static
	{
		if (!in_array($direction, [Sort::ASCENDING, Sort::DESCENDING], true)) {
			throw new Exception("Invalid sort direction '$direction'");
		}
		$this->_sortDirection = $direction;
		return $this;
	}

	/**
	 * Set a secondary sort to apply if two elements are equivilant.
	 *
	 * If, when compared with the sort, 2 elements come back as '0'
	 * (equivilant) then the secondary sort will be applied. This secondary
	 * sort can, itself, have a secondary sort so creating a sort chain.
	 *
	 * @param Sort $sort The secondary sort.
	 *
	 * @return static
	 */
	public function setSecondarySort(?Sort $sort): static
	{
		$this->_secondarySort = $sort;
		return $this;
	}

	/**
	 * Return secondary sort, if available.
	 *
	 * @return Sort|null
	 */
	public function getSecondarySort(): ?Sort
	{
		return $this->_secondarySort;
	}
}
