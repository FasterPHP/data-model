<?php
/**
 * Base Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use Stringable;

/**
 * Base Field class.
 */
abstract class Base implements Stringable
{
	protected string $_name;
	protected $_value;

	public function __construct(string $name)
	{
		$this->_name = $name;
	}

	public function getName(): string
	{
		return $this->_name;
	}

	public function isset(): bool
	{
		return isset($this->_value);
	}

	public function setValue($value): self
	{
		$this->_setValue($value);
		return $this;
	}

	public function getValue()
	{
		return $this->_value;
	}

	public function getSqlValue()
	{
		return $this->getValue();
	}

	public function __toString(): string
	{
		return strval($this->getValue());
	}

	abstract protected function _setValue($value): void;
}
