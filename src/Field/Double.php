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
		if (!is_numeric($value)) {
			throw new InvalidArgumentException("{$this->_name} value '$value' must be a number");
		}
		$this->_value = doubleval($value);
	}
}
