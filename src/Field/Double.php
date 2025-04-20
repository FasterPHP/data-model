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
    public function setValue($value): self
    {
        if (null === $value) {
            $this->value = $value;
        } elseif (is_numeric($value)) {
            $this->value = doubleval($value);
        } else {
            throw new InvalidArgumentException("{$this->name} value '$value' must be a number");
        }
        return $this;
    }
}
