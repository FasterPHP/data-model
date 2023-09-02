<?php
/**
 * Boolean Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;

/**
 * Boolean Field class.
 */
class Boolean extends Base
{
	public function getSqlValue()
	{
		return $this->_value ? 'y' : 'n';
	}

	protected function _setValue($value): void
	{
		if (in_array($value, [true, 'y', 1, '1'], true)) {
			$this->_value = true;
		} elseif (in_array($value, [false, 'n', 0, '0'], true)) {
			$this->_value = false;
		} else {
			throw new InvalidArgumentException("{$this->_name} value '$value' cannot be converted to boolean");
		}
	}
}
