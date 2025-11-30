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
    public function getSqlValue(): mixed
    {
        return is_null($this->value) ? null : ($this->value ? 'y' : 'n');
    }

    protected function setValueInternal(mixed $value): self
    {
        if (null === $value) {
            $this->value = $value;
        } elseif (in_array($value, [true, 'y', 1, '1'], true)) {
            $this->value = true;
        } elseif (in_array($value, [false, 'n', 0, '0'], true)) {
            $this->value = false;
        } else {
            throw new InvalidArgumentException("{$this->name} value '$value' cannot be converted to boolean");
        }
        return $this;
    }
}
