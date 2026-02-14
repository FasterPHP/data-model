<?php

/**
 * Validatable Trait.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Validation;

use FasterPhp\DataModel\Exception;

/**
 * Validatable Trait.
 *
 * Provides validation functionality for Item classes.
 * Discovers validate{FieldName}() methods by convention.
 */
trait ValidatableTrait
{
    protected bool $isValid;
    protected array $validationErrors;

    /**
     * Check if item passes validation.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if (!isset($this->isValid)) {
            $this->validate();
        }
        return $this->isValid;
    }

    /**
     * Run validation on the item.
     *
     * @return void
     */
    public function validate(): void
    {
        $this->isValid = true;
        $this->validationErrors = [];

        foreach (array_keys(static::FIELDS) as $fieldName) {
            $method = 'validate' . ucfirst($fieldName);
            if (!method_exists($this, $method)) {
                continue;
            }

            $chain = $this->$method();

            if ($chain->isValid($this->getField($fieldName)->getValue())) {
                unset($this->validationErrors[$fieldName]);
            } else {
                $this->isValid = false;
                $this->validationErrors[$fieldName] = array_values($chain->getMessages());
            }
        }
    }

    /**
     * Get validation error messages.
     *
     * @return array
     * @throws Exception if item has not been validated
     */
    public function getValidationErrors(): array
    {
        if (!isset($this->validationErrors)) {
            throw new Exception('Item not validated');
        }
        return $this->validationErrors;
    }
}
