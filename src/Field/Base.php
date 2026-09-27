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
    protected mixed $value = null;

    /**
     * Constructor.
     *
     * @param string $name Field name
     * @param mixed $initialValue Optional initial value; an explicitly supplied null is set, an omitted one is not
     */
    public function __construct(string $name, mixed $initialValue = null)
    {
        $this->name = $name;

        // Set initial value only if explicitly provided, so an explicit null is distinguishable from none
        if (func_num_args() >= 2) {
            $this->setValueInternal($initialValue);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isset(): bool
    {
        return isset($this->value);
    }

    abstract protected function setValueInternal(mixed $value): self;

    /**
     * Set the field value.
     *
     * Access control is enforced by Item::setValue(), not here.
     */
    public function setValue(mixed $value): self
    {
        return $this->setValueInternal($value);
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getSqlValue(): mixed
    {
        return $this->getValue();
    }

    public function __toString(): string
    {
        return strval($this->getValue());
    }
}
