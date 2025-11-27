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
    protected function setValueInternal($value): self
    {
        if (!is_null($value) && !is_int($value) && intval($value) != $value) {
            throw new InvalidArgumentException("{$this->name} value '$value' must be an integer");
        }
        $this->value = is_null($value) ? $value : intval($value);
        return $this;
    }
}
