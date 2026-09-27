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
    public function getSqlValue(): mixed
    {
        return is_null($this->value) ? null : $this->value->format('Y-m-d H:i:s');
    }

    protected function setValueInternal(mixed $value): self
    {
        if (null === $value || $value instanceof \DateTime) {
            $this->value = $value;
        } elseif (is_string($value)) {
            $parsed = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
            if ($parsed === false) {
                throw new InvalidArgumentException(
                    "{$this->name} value '$value' is not a valid datetime (expected Y-m-d H:i:s)"
                );
            }
            $this->value = $parsed;
        } else {
            throw new InvalidArgumentException("{$this->name} value must be a DateTime instance or string");
        }
        return $this;
    }

    public function __toString(): string
    {
        return is_null($this->value) ? '' : $this->value->format('d/m/Y');
    }
}
