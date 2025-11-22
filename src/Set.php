<?php

/**
 * Data Model Set class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use JsonSerializable;
use OutOfBoundsException;
use SeekableIterator;
use Stringable;

/**
 * Data Model Set class.
 */
abstract class Set implements ArrayAccess, Countable, JsonSerializable, SeekableIterator, Stringable
{
    protected array $data;
    protected string $itemClassName;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $this->itemClassName = Util::getItemClassName(get_called_class());
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->getValues();
    }

    public function __toString(): string
    {
        return json_encode($this->getValues());
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function getValues(): array
    {
        $values = [];
        for ($i = 0; $i < count($this->data); $i++) {
            $values[] = $this->getItem($i)->getValues();
        }
        return $values;
    }

    public function createItem(): Item
    {
        $item = new $this->itemClassName();
        $this->addItem($item);
        return $item;
    }

    public function addItem(Item $item, $offset = null): void
    {
        if (!$item instanceof $this->itemClassName) {
            throw new InvalidArgumentException('Cannot add ' . get_class($item) . ' to ' . get_called_class());
        }

        if (is_null($offset)) {
            $this->data[] = $item;
        } else {
            $this->data[$offset] = $item;
        }
    }

    public function setToDeleteAll(): static
    {
        foreach (array_keys($this->data) as $offset) {
            $this->getItem($offset)->setToDelete();
        }
        return $this;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): ?Item
    {
        if (is_null($offset)) {
            return null;
        } elseif (!isset($this->data[$offset])) {
            throw new OutOfBoundsException("Invalid offset $offset");
        }

        return $this->getItem($offset);
    }

    public function offsetSet($offset, $item): void
    {
        $this->addItem($item, $offset);
    }

    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    public function seek($position): void
    {
        if (!isset($this->data[$position])) {
            throw new OutOfBoundsException("Invalid seek position ($position)");
        }
        reset($this->data);
        while ($position !== key($this->data)) {
            next($this->data);
        };
    }

    public function current(): Item|false
    {
        if (false === current($this->data)) {
            return false;
        }

        return $this->getItem(key($this->data));
    }

    public function key(): mixed
    {
        return key($this->data);
    }

    public function next(): void
    {
        next($this->data);
    }

    public function rewind(): void
    {
        reset($this->data);
    }

    public function valid(): bool
    {
        return false !== current($this->data);
    }

    /**
     * Return an array of values, optionally using properties for the key and/or value.
     *
     * You can specify a property to use as the key, or null for a sequential array, a property
     * to use as the value, or null to return the whole model as the value.
     *
     * Examples of use:
     * $set->arrayMap('id', 'name'); // [1 => 'Bob', 3 => 'Frank']
     * $set->arrayMap(null, 'name'); // [0 => 'Bob', 1 => 'Frank']
     * $set->arrayMap('id'); // [1 => <Model Object>, 3 => <Model Object>]
     *
     * @param ?string $key   Optional name of the property to use as the key, or null for a 0-indexed array.
     * @param ?string $value The name of the property to use as the value, or null to use the model object as value.
     *
     * @return array
     */
    public function arrayMap(string $key = null, string $value = null): array
    {
        if ($key !== null) {
            $keyFunction = 'get' . ucfirst($key);
        }

        if ($value !== null) {
            $valueFunction = 'get' . ucfirst($value);
        }

        $result = [];
        foreach ($this as $item) {
            if ($value === null) {
                $currentValue = $item;
            } else {
                $currentValue = $item->$valueFunction();
            }

            if ($key === null) {
                $result[] = $currentValue;
            } else {
                $result[$item->$keyFunction()] = $currentValue;
            }
        }
        return $result;
    }

    protected function getItem(int $offset): Item
    {
        if (is_array($this->data[$offset])) {
            $this->data[$offset] = new $this->itemClassName($this->data[$offset]);
        }
        if (!$this->data[$offset] instanceof $this->itemClassName) {
            throw new Exception('Invalid item in set: ' . json_encode($this->data[$offset]));
        }
        return $this->data[$offset];
    }
}
