<?php

/**
 * Data Model Item class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use BadMethodCallException;
use Laminas\Validator;
use Stringable;
use FasterPhp\DataModel\Field;

/**
 * Data Model Item class.
 */
abstract class Item implements Stringable
{
    public const ID_FIELD = '';
    public const ID_INTERNAL = 'id';
    public const FIELDS = [];
    public const FIELDS_READONLY = [];
    public const FIELDS_EXTERNAL = [];
    public const FIELDS_AGGREGATE = [];
    public const DEFAULTS = [];
    public const VALIDATORS = [];
    protected array $data;
    protected array $originalValues = [];
    protected bool $toDelete = false;
    protected bool $isValid;
    protected array $validationErrors;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function getRawData(): array
    {
        return $this->data;
    }

    public function getValues(): array
    {
        $values = [];
        foreach (array_keys(static::FIELDS) as $fieldName) {
            $values[$fieldName] = $this->getField($fieldName)->getValue();
        }
        return $values;
    }

    public function getSqlValues($includeNull = true): array
    {
        $values = [];
        foreach (array_merge(array_keys(static::FIELDS), array_keys(static::FIELDS_READONLY)) as $fieldName) {
            $sqlValue = $this->getField($fieldName)->getSqlValue();
            if (true === $includeNull || null !== $sqlValue) {
                $values[$fieldName] = $sqlValue;
            }
        }
        return $values;
    }

    public function isTemp(): bool
    {
        if (empty($this->data[static::ID_INTERNAL])) {
            return true;
        } elseif (
            array_key_exists(static::ID_INTERNAL, $this->originalValues)
            && is_null($this->originalValues[static::ID_INTERNAL])
        ) {
            return true;
        }
        return false;
    }

    public function isDirty(): bool
    {
        return !empty($this->originalValues);
    }

    public function setToDelete(bool $toDelete = true): static
    {
        $this->toDelete = $toDelete;
        return $this;
    }

    public function isToDelete(): bool
    {
        return $this->toDelete;
    }

    public function getChangedSqlValues(): array
    {
        $sqlValues = [];
        foreach (array_keys($this->originalValues) as $fieldName) {
            $sqlValues[$fieldName] = $this->getField($fieldName)->getSqlValue();
        }
        return $sqlValues;
    }

    public function hasFieldChanged(string $fieldName): bool
    {
        return isset($this->originalValues[$fieldName]);
    }

    public function clearOriginalValues(): static
    {
        $this->originalValues = [];
        return $this;
    }

    public function isValid(): bool
    {
        if (!isset($this->isValid)) {
            $this->validate();
        }
        return $this->isValid;
    }

    public function validate(): void
    {
        $this->isValid = true;
        $this->validationErrors = [];
        foreach (static::VALIDATORS as $fieldName => $validators) {
            $validatorChain = new Validator\ValidatorChain();
            foreach ($validators as $args) {
                $this->addValidator($validatorChain, $fieldName, $args);
            }

            if ($validatorChain->isValid($this->getField($fieldName)->getValue())) {
                unset($this->validationErrors[$fieldName]);
            } else {
                $this->isValid = false;
                $this->validationErrors[$fieldName] = array_values($validatorChain->getMessages());
            }
        }
    }

    public function getValidationErrors(): array
    {
        if (!isset($this->validationErrors)) {
            throw new Exception('Item not validated');
        }
        return $this->validationErrors;
    }

    public function serialize(): string
    {
        return serialize($this->getValues());
    }

    public function unserialize(string $data): void
    {
        $this->data = unserialize($data);
    }

    public function __serialize(): array
    {
        return $this->getValues();
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }

    public function __toString(): string
    {
        return json_encode($this->getValues());
    }

    public function __call(string $name, array $args): mixed
    {
        if (count($args) === 1 && array_key_exists(0, $args) && preg_match('/^set(.+)$/', $name, $matches)) {
            $this->setValue(lcfirst($matches[1]), $args[0]);
            return $this;
        } elseif (count($args) === 0 && preg_match('/^get(.+)$/', $name, $matches)) {
            return $this->getValue(lcfirst($matches[1]));
        }
        throw new BadMethodCallException("Call to undefined method '$name'");
    }

    protected function getValue($fieldName): mixed
    {
        return $this->getField($fieldName)->getValue();
    }

    protected function setValue(string $fieldName, $value): Field\Base
    {
        $field = $this->getField($fieldName);
        $oldValue = isset($this->originalValues[$fieldName]) ? $this->originalValues[$fieldName] : $field->getValue();
        $field->setValue($value);
        $newValue = $field->getValue();
        if ($newValue === $oldValue) {
            unset($this->originalValues[$fieldName]);
        } else {
            $this->originalValues[$fieldName] = $oldValue;
        }
        unset($this->isValid);
        return $field;
    }

    protected function getField(string $fieldName): Field\Base
    {
        if (
            !isset(static::FIELDS[$fieldName])
            && !isset(static::FIELDS_READONLY[$fieldName])
            && !isset(static::FIELDS_EXTERNAL[$fieldName])
            && !isset(static::FIELDS_AGGREGATE[$fieldName])
        ) {
            throw new Exception("Field '$fieldName' not defined");
        }
        if (!array_key_exists($fieldName, $this->data) || !$this->data[$fieldName] instanceof Field\Base) {
            $fieldClassName = static::FIELDS[$fieldName]
                ?? static::FIELDS_READONLY[$fieldName]
                ?? static::FIELDS_EXTERNAL[$fieldName]
                ?? static::FIELDS_AGGREGATE[$fieldName];
            $field = new $fieldClassName($fieldName);
            if (array_key_exists($fieldName, $this->data)) {
                $field->setValue($this->data[$fieldName]);
            } elseif (array_key_exists($fieldName, static::DEFAULTS)) {
                if ($fieldName === static::ID_INTERNAL) {
                    throw new Exception('Cannot set default id, please omit from DEFAULTS');
                }
                $field->setValue(static::DEFAULTS[$fieldName]);
            } elseif ($fieldName === 'id' && $field->getValue() === 0) {
                $field->setValue(null);
            }
            $this->data[$fieldName] = $field;
        }
        return $this->data[$fieldName];
    }

    protected function addValidator(Validator\ValidatorChain $validatorChain, string $fieldName, array $args): void
    {
        if (!isset($args['class'])) {
            throw new Exception("Validator class name missing for field '$fieldName'");
        }

        if (
            isset($args['skipIfEmpty'])
            && true === $args['skipIfEmpty']
            && empty($this->getField($fieldName)->getValue())
        ) {
            return;
        }

        $options = $args['options'] ?? [];
        // If using callback validator, add item instance as last callback option
        if ($args['class'] == Validator\Callback::class) {
            if (!isset($options['callbackOptions'])) {
                $options['callbackOptions'] = [];
            }
            $options['callbackOptions'][] = $this;
        }

        $validator = new $args['class']($options);
        if (isset($args['message'])) {
            $validator->setMessage($args['message']);
        }

        $validatorChain->attach(
            $validator,
            breakChainOnFailure: $args['break'] ?? null,
            priority: $args['priority'] ?? null
        );
    }
}
