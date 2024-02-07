<?php
/**
 * Double Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;

/**
 * Double Field class.
 */
class Double extends Base
{
	protected $_value = 0.0;

	protected function _setValue($value): void
	{
		if (null === $value) {
			$this->_value = $value;
		} elseif (is_numeric($value)) {
			$this->_value = doubleval($value);
		} else {
			throw new InvalidArgumentException("{$this->_name} value '$value' must be a number");
		}
	}
}
