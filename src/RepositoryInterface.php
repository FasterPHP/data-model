<?php

/**
 * Repository Interface.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Repository Interface.
 *
 * Defines the contract for data model repositories.
 */
interface RepositoryInterface
{
    /**
     * Set the sort order for queries.
     *
     * @param Sort|null $sort
     * @return static
     */
    public function setSort(?Sort $sort): static;

    /**
     * Set maximum items per page for pagination.
     *
     * @param int|null $max
     * @return static
     */
    public function setMaxItemsPerPage(?int $max): static;

    /**
     * Get the database name.
     *
     * @return string
     * @throws Exception if not set
     */
    public function getDbName(): string;

    /**
     * Get the table name.
     *
     * @return string
     * @throws Exception if not set
     */
    public function getTableName(): string;

    /**
     * Get the ID field name.
     *
     * @return string
     * @throws Exception if not set
     */
    public function getIdField(): string;

    /**
     * Get a single item by its ID.
     *
     * @param mixed $id
     * @return ItemInterface|null
     */
    public function getItemWithId(mixed $id): ?ItemInterface;

    /**
     * Get a single item matching the given parameters.
     *
     * A sort given here orders this lookup alone and replaces the repository's sort for it; the
     * repository's sort is never changed. Without one, the lookup uses the repository's sort.
     *
     * @param array $params Field => value pairs
     * @param array $types Field => comparison type pairs
     * @param Sort|null $sort Order for this lookup only, in place of the repository's sort
     * @return ItemInterface|null
     */
    public function getItemWithParams(array $params, array $types = [], ?Sort $sort = null): ?ItemInterface;

    /**
     * Get all items.
     *
     * @return SetInterface
     */
    public function getSetOfAll(): SetInterface;

    /**
     * Get items matching the given parameters.
     *
     * @param array $params Field => value pairs
     * @param array $types Field => comparison type pairs
     * @return SetInterface
     */
    public function getSetWithParams(array $params, array $types = []): SetInterface;

    /**
     * Save a set of items (insert/update/delete as needed).
     *
     * @param SetInterface $set
     * @param bool $useTransaction Wrap operations in a transaction, or join the one already active
     * @return void
     */
    public function saveSet(SetInterface $set, bool $useTransaction = false): void;

    /**
     * Save a single item (insert/update/delete as needed).
     *
     * @param ItemInterface $item
     * @param bool $useTransaction Wrap operation in a transaction, or join the one already active
     * @return void
     */
    public function saveItem(ItemInterface $item, bool $useTransaction = false): void;
}
