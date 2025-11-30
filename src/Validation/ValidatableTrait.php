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
 * Requires implementing class to provide buildValidatorChain() method.
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

        if (!defined('static::VALIDATORS')) {
            return;
        }

        foreach (static::VALIDATORS as $fieldName => $validators) {
            $chain = $this->buildValidatorChain($fieldName, $validators);

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

    /**
     * Build a validator chain for a field. Must be implemented by a concrete trait
     * (e.g., LaminasValidatorTrait) or overridden in the Item class.
     *
     * @param string $fieldName The field name to validate
     * @param array $configs Array of validator configurations
     * @return mixed Object with isValid() and getMessages() methods
     */
    abstract protected function buildValidatorChain(string $fieldName, array $configs);
}
