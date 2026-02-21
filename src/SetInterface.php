<?php

/**
 * Set Interface.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use ArrayAccess;
use Countable;
use JsonSerializable;
use SeekableIterator;
use Stringable;

/**
 * Set Interface.
 *
 * Defines the contract for data model collections.
 *
 * @extends ArrayAccess<int, ItemInterface>
 * @extends SeekableIterator<int, ItemInterface>
 */
interface SetInterface extends ArrayAccess, Countable, JsonSerializable, SeekableIterator, Stringable
{
    /**
     * Get the raw data array.
     *
     * @return array
     */
    public function getRawData(): array;

    /**
     * Get all items' values as an array.
     *
     * @return array
     */
    public function getValues(): array;

    /**
     * Create a new item and add it to the set.
     *
     * @return ItemInterface
     */
    public function createItem(): ItemInterface;

    /**
     * Add an existing item to the set.
     *
     * @param ItemInterface $item
     * @param int|null $offset
     * @return void
     */
    public function addItem(ItemInterface $item, $offset = null): void;

    /**
     * Mark all items in the set for deletion.
     *
     * @return static
     */
    public function setToDeleteAll(): static;

    /**
     * Check if the set is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Map items to an array with optional key/value properties.
     *
     * @param string|null $key Property name for array keys, or null for sequential
     * @param string|null $value Property name for array values, or null for item objects
     * @return array
     */
    public function arrayMap(?string $key = null, ?string $value = null): array;
}
