<?php

/**
 * Data Model Item class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use BadMethodCallException;

/**
 * Data Model Item class.
 */
abstract class Item implements ItemInterface
{
    public const ID_FIELD = '';
    public const ID_INTERNAL = 'id';
    public const ID_TYPE = Field\Integer::class;
    public const FIELDS = [];
    public const FIELDS_READONLY = [];
    public const FIELDS_EXTERNAL = [];
    public const FIELDS_AGGREGATE = [];
    public const DEFAULTS = [];

    private const ITEM_STATE_TEMP = 'temp';
    private const ITEM_STATE_CURRENT = 'current';
    private const ITEM_STATE_MODIFIED = 'modified';

    protected array $data;
    protected array $originalValues = [];
    protected bool $toDelete = false;
    private string $itemState;

    public function __construct(array $data = [], bool $isTemp = true)
    {
        if ($isTemp && !empty($data[static::ID_INTERNAL])) {
            throw new Exception("Cannot construct a temporary item with an id value");
        }
        $this->data = $data;
        $this->itemState = $isTemp ? self::ITEM_STATE_TEMP : self::ITEM_STATE_CURRENT;
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function getValues(): array
    {
        $values = [];
        foreach (array_keys(static::FIELDS) as $fieldName) {
            $values[$fieldName] = $this->getField($fieldName)->getValue();
        }
        return $values;
    }

    public function getSqlValues($includeNull = true): array
    {
        $values = [];
        foreach (array_merge(array_keys(static::FIELDS), array_keys(static::FIELDS_READONLY)) as $fieldName) {
            $sqlValue = $this->getField($fieldName)->getSqlValue();
            if (true === $includeNull || null !== $sqlValue) {
                $values[$fieldName] = $sqlValue;
            }
        }
        return $values;
    }

    public function isTemp(): bool
    {
        return $this->itemState === self::ITEM_STATE_TEMP;
    }

    public function isDirty(): bool
    {
        return $this->itemState === self::ITEM_STATE_MODIFIED;
    }

    public function setToDelete(bool $toDelete = true): static
    {
        $this->toDelete = $toDelete;
        return $this;
    }

    public function isToDelete(): bool
    {
        return $this->toDelete;
    }

    public function getChangedSqlValues(): array
    {
        $sqlValues = [];
        foreach (array_keys($this->originalValues) as $fieldName) {
            $sqlValues[$fieldName] = $this->getField($fieldName)->getSqlValue();
        }
        return $sqlValues;
    }

    public function hasFieldChanged(string $fieldName): bool
    {
        return isset($this->originalValues[$fieldName]);
    }

    public function markItemPersisted(mixed $id = null): void
    {
        if ($id !== null) {
            if ($this->itemState !== self::ITEM_STATE_TEMP) {
                throw new Exception("Cannot set id on a non-temporary item");
            }
            $this->getField(static::ID_INTERNAL)->setValue($id);
        }
        $this->originalValues = [];
        $this->itemState = self::ITEM_STATE_CURRENT;
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return [static::ID_INTERNAL => $this->getId()] + $this->getValues();
    }

    public function __serialize(): array
    {
        return [
            'values' => [static::ID_INTERNAL => $this->getId()] + $this->getValues(),
            'originalValues' => $this->originalValues,
            'toDelete' => $this->toDelete,
        ];
    }

    public function __unserialize(array $serialized): void
    {
        $this->data = $serialized['values'];
        $this->originalValues = $serialized['originalValues'];
        $this->toDelete = $serialized['toDelete'];

        $hasId = !empty($this->data[static::ID_INTERNAL]);
        if (!$hasId) {
            $this->itemState = self::ITEM_STATE_TEMP;
        } elseif (!empty($this->originalValues)) {
            $this->itemState = self::ITEM_STATE_MODIFIED;
        } else {
            $this->itemState = self::ITEM_STATE_CURRENT;
        }
    }

    #[\Override]
    public function __toString(): string
    {
        return json_encode($this->jsonSerialize());
    }

    public function __call(string $name, array $args): mixed
    {
        if (count($args) === 1 && array_key_exists(0, $args) && preg_match('/^set(.+)$/', $name, $matches)) {
            $fieldName = lcfirst($matches[1]);
            if ($fieldName === static::ID_INTERNAL) {
                throw new Exception(
                    "Cannot set id directly; the id field is managed automatically"
                );
            }
            $this->setValue($fieldName, $args[0]);
            return $this;
        } elseif (count($args) === 0 && preg_match('/^get(.+)$/', $name, $matches)) {
            return $this->getFieldValue(lcfirst($matches[1]));
        }
        throw new BadMethodCallException("Call to undefined method '$name'");
    }

    protected function getFieldValue($fieldName): mixed
    {
        return $this->getField($fieldName)->getValue();
    }

    protected function setValue(string $fieldName, $value): Field\Base
    {
        $field = $this->getField($fieldName);
        if (
            isset(static::FIELDS_READONLY[$fieldName])
            || isset(static::FIELDS_EXTERNAL[$fieldName])
            || isset(static::FIELDS_AGGREGATE[$fieldName])
        ) {
            throw new Exception("Cannot update value for read-only field '$fieldName'");
        }
        $oldValue = isset($this->originalValues[$fieldName]) ? $this->originalValues[$fieldName] : $field->getValue();
        $field->setValue($value);
        $newValue = $field->getValue();
        if ($newValue === $oldValue) {
            unset($this->originalValues[$fieldName]);
        } else {
            $this->originalValues[$fieldName] = $oldValue;
        }
        if ($this->itemState === self::ITEM_STATE_CURRENT && !empty($this->originalValues)) {
            $this->itemState = self::ITEM_STATE_MODIFIED;
        } elseif ($this->itemState === self::ITEM_STATE_MODIFIED && empty($this->originalValues)) {
            $this->itemState = self::ITEM_STATE_CURRENT;
        }
        unset($this->isValid);
        return $field;
    }

    protected function getField(string $fieldName): Field\Base
    {
        $isIdField = ($fieldName === static::ID_INTERNAL);

        // Migration guard: id must not be declared in FIELDS
        if ($isIdField && isset(static::FIELDS[$fieldName])) {
            throw new Exception(
                "Do not declare '" . static::ID_INTERNAL . "' in FIELDS — it is managed automatically. "
                . "Remove '" . static::ID_INTERNAL . "' from FIELDS and optionally set ID_TYPE "
                . "to specify the field type."
            );
        }

        if (
            !$isIdField
            && !isset(static::FIELDS[$fieldName])
            && !isset(static::FIELDS_READONLY[$fieldName])
            && !isset(static::FIELDS_EXTERNAL[$fieldName])
            && !isset(static::FIELDS_AGGREGATE[$fieldName])
        ) {
            throw new Exception("Field '$fieldName' not defined");
        }
        if (!array_key_exists($fieldName, $this->data) || !$this->data[$fieldName] instanceof Field\Base) {
            if ($isIdField) {
                $fieldClassName = static::ID_TYPE;
                $isReadonly = false;
            } else {
                $fieldClassName = static::FIELDS[$fieldName]
                    ?? static::FIELDS_READONLY[$fieldName]
                    ?? static::FIELDS_EXTERNAL[$fieldName]
                    ?? static::FIELDS_AGGREGATE[$fieldName];
                $isReadonly = isset(static::FIELDS_READONLY[$fieldName])
                    || isset(static::FIELDS_EXTERNAL[$fieldName])
                    || isset(static::FIELDS_AGGREGATE[$fieldName]);
            }

            // Determine initial value
            $initialValue = null;
            $hasInitialValue = false;
            if (array_key_exists($fieldName, $this->data)) {
                $initialValue = $this->data[$fieldName];
                $hasInitialValue = true;
            } elseif (array_key_exists($fieldName, static::DEFAULTS)) {
                if ($isIdField) {
                    throw new Exception('Cannot set default id, please omit from DEFAULTS');
                }
                $initialValue = static::DEFAULTS[$fieldName];
                $hasInitialValue = true;
            } elseif ($isIdField) {
                // For id field, we want to set null if no value provided
                $initialValue = null;
                $hasInitialValue = true;
            }

            // Create field with initial value (bypasses readonly check)
            if ($hasInitialValue) {
                $field = new $fieldClassName($fieldName, $isReadonly, $initialValue);
            } else {
                $field = new $fieldClassName($fieldName, $isReadonly);
            }

            $this->data[$fieldName] = $field;
        }
        return $this->data[$fieldName];
    }
}
