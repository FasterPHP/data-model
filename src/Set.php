<?php
/**
 * Data Model Set class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use SeekableIterator;
use OutOfBoundsException;

/**
 * Data Model Set class.
 */
abstract class Set implements ArrayAccess, Countable, SeekableIterator
{
	protected array $_data;
	protected string $_itemClassName;

	public function __construct(array $data = [])
	{
		$this->_data = $data;
		$this->_itemClassName = Util::getItemClassName(get_called_class());
	}

	public function getRawData(): array
	{
		return $this->_data;
	}

	public function createItem(): Item
	{
		$item = new $this->_itemClassName();
		$this->addItem($item);
		return $item;
	}

	public function addItem(Item $item, $offset = null): void
	{
		if (!$item instanceof $this->_itemClassName) {
			throw new InvalidArgumentException('Cannot add ' . get_class($item) . ' to ' . get_called_class());
		}

		if (is_null($offset)) {
			$this->_data[] = $item;
		} else {
			$this->_data[$offset] = $item;
		}
	}

	public function setToDeleteAll(): static
	{
		foreach (array_keys($this->_data) as $offset) {
			$this->_getItem($offset)->setToDelete();
		}
		return $this;
	}

	public function count(): int
	{
		return count($this->_data);
	}

	public function isEmpty(): bool
	{
		return 0 === $this->count();
	}

	public function offsetExists($offset): bool
	{
		return isset($this->_data[$offset]);
	}

	public function offsetGet($offset): ?Item
	{
		if (is_null($offset)) {
			return null;
		} elseif (!isset($this->_data[$offset])) {
			throw new OutOfBoundsException("Invalid offset $offset");
		}

		return $this->_getItem($offset);
	}

	public function offsetSet($offset, $item): void
	{
		$this->addItem($item, $offset);
	}

	public function offsetUnset($offset): void
	{
		unset($this->_data[$offset]);
	}

	public function seek($position): void
	{
		if (!isset($this->_data[$position])) {
			throw new OutOfBoundsException("Invalid seek position ($position)");
		}
		reset($this->_data);
		while ($position !== key($this->_data)) {
			next($this->_data);
		};
	}

	public function current(): Item|false
	{
		if (false === current($this->_data)) {
			return false;
		}

		return $this->_getItem(key($this->_data));
	}

	public function key(): mixed
	{
		return key($this->_data);
	}

	public function next(): void
	{
		next($this->_data);
	}

	public function rewind(): void
	{
		reset($this->_data);
	}

	public function valid(): bool
	{
		return false !== current($this->_data);
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

	protected function _getItem(int $offset): Item
	{
		if (is_array($this->_data[$offset])) {
			$this->_data[$offset] = new $this->_itemClassName($this->_data[$offset]);
		}
		if (!$this->_data[$offset] instanceof $this->_itemClassName) {
			throw new Exception('Invalid item in set: ' . json_encode($this->_data[$offset]));
		}
		return $this->_data[$offset];
	}
}
