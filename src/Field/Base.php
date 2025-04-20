<?php

/**
 * Base Field class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use Stringable;

/**
 * Base Field class.
 */
abstract class Base implements Stringable
{
    protected string $name;
    protected $value;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isset(): bool
    {
        return isset($this->value);
    }

    public function setValue($value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getSqlValue()
    {
        return $this->getValue();
    }

    public function __toString(): string
    {
        return strval($this->getValue());
    }
}
