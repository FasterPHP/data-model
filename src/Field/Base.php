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
    protected bool $isReadonly = false;

    /**
     * Constructor.
     *
     * @param string $name Field name
     * @param bool $isReadonly Whether the field is readonly
     * @param mixed $initialValue Optional initial value to set (bypasses readonly check)
     */
    public function __construct(string $name, bool $isReadonly = false, mixed $initialValue = null)
    {
        $this->name = $name;
        $this->isReadonly = $isReadonly;

        // Set initial value if provided (bypasses readonly check)
        if (func_num_args() >= 3) {
            $this->setValueInternal($initialValue);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setReadonly(bool $isReadonly): self
    {
        $this->isReadonly = $isReadonly;
        return $this;
    }

    public function isReadonly(): bool
    {
        return $this->isReadonly;
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
