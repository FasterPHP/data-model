<?php

/**
 * Laminas Validator Trait.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Validation;

use FasterPhp\DataModel\Exception;
use Laminas\Validator;

/**
 * Laminas Validator Trait.
 *
 * Provides Laminas-specific validation implementation.
 * Requires laminas/laminas-validator package.
 */
trait LaminasValidatorTrait
{
    /**
     * Build a validator chain for a field using Laminas validators.
     *
     * @param string $fieldName The field name to validate
     * @param array $configs Array of validator configurations
     * @return Validator\ValidatorChain
     */
    protected function buildValidatorChain(string $fieldName, array $configs): Validator\ValidatorChain
    {
        $chain = new Validator\ValidatorChain();
        foreach ($configs as $args) {
            $this->addValidator($chain, $fieldName, $args);
        }
        return $chain;
    }

    /**
     * Add a validator to the chain.
     *
     * @param Validator\ValidatorChain $validatorChain
     * @param string $fieldName
     * @param array $args
     * @return void
     * @throws Exception
     */
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
