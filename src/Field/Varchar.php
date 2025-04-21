<?php

/**
 * Varchar Field class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;

/**
 * Varchar Field class.
 */
class Varchar extends Base
{
    public function setValue($value): self
    {
        if (
            !is_null($value)
            && !is_string($value)
            && (is_array($value)
            || (is_object($value) && !($value instanceof \Stringable))
            || strval($value) != $value)
        ) {
            throw new InvalidArgumentException("{$this->name} value must be a string");
        }
        $this->value = is_null($value) ? $value : strval($value);
        return $this;
    }
}
