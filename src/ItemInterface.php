<?php

/**
 * Item Interface.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use JsonSerializable;
use Stringable;

/**
 * Item Interface.
 *
 * Defines the contract for data model items.
 */
interface ItemInterface extends Stringable, JsonSerializable
{
    /**
     * Get the raw data array.
     *
     * @return array
     */
    public function getRawData(): array;

    /**
     * Get all field values as an associative array.
     *
     * @return array
     */
    public function getValues(): array;

    /**
     * Get all field values formatted for SQL.
     *
     * @param bool $includeNull Whether to include null values
     * @return array
     */
    public function getSqlValues(bool $includeNull = true): array;

    /**
     * Check if item is temporary (not yet persisted).
     *
     * @return bool
     */
    public function isTemp(): bool;

    /**
     * Check if item has been modified since loading.
     *
     * @return bool
     */
    public function isDirty(): bool;

    /**
     * Mark item for deletion.
     *
     * @param bool $toDelete
     * @return static
     */
    public function setToDelete(bool $toDelete = true): static;

    /**
     * Check if item is marked for deletion.
     *
     * @return bool
     */
    public function isToDelete(): bool;

    /**
     * Get SQL values only for changed fields.
     *
     * @return array
     */
    public function getChangedSqlValues(): array;

    /**
     * Check if a specific field has changed.
     *
     * @param string $fieldName
     * @return bool
     */
    public function hasFieldChanged(string $fieldName): bool;
}
