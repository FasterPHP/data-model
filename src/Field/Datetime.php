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
    protected $value = null;

    public function getSqlValue()
    {
        return is_null($this->value) ? null : $this->value->format('Y-m-d H:i:s');
    }

    public function setValue($value): self
    {
        if (null === $value || $value instanceof \DateTime) {
            $this->value = $value;
        } elseif (is_string($value)) {
            $this->value = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        } else {
            throw new InvalidArgumentException("{$this->name} value must be a DateTime instance or string");
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->value->format('d/m/Y');
    }
}
