<?php
/**
 * Datetime Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

//use DateTime;
use InvalidArgumentException;

/**
 * Datetime Field class.
 */
class Datetime extends Base
{
	protected $_value = null;

	public function getSqlValue()
	{
		return is_null($this->_value) ? null : $this->_value->format('Y-m-d H:i:s');
	}

	protected function _setValue($value): void
	{
		if ($value instanceof \DateTime) {
			$this->_value = $value;
		} elseif (is_string($value)) {
			$this->_value = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
		} else {
			throw new InvalidArgumentException("{$this->_name} value '$value' must be a DateTime instance of string");
		}
	}
}
