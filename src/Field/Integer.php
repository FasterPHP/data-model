<?php
/**
 * Integer Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;

/**
 * Integer Field class.
 */
class Integer extends Base
{
	protected function _setValue($value): void
	{
		if (!is_int($value) && intval($value) != $value) {
			throw new InvalidArgumentException("{$this->_name} value '$value' must be an integer");
		}
		$this->_value = intval($value);
	}
}
