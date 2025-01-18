<?php
/**
 * JSON Field class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;
use JsonException;

/**
 * JSON Field class.
 */
class Json extends Base
{
	protected function _setValue($value): void
	{
		if (is_null($value)) {
			$this->_value = $value;

		// Decode value
		} elseif (is_string($value)) {
			try {
				$this->_value = json_decode($value, true, JSON_THROW_ON_ERROR);
			} catch (JsonException $ex) {
				throw new InvalidArgumentException($ex->getMessage());
			}

		// Encode value
		} else {
			try {
				$this->_value = json_encode($value, JSON_THROW_ON_ERROR);
			} catch (JsonException $ex) {
				throw new InvalidArgumentException($ex->getMessage());
			}
		}
	}
}
