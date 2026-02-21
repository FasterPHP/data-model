<?php

/**
 * Laminas Validator Trait.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Validation;

use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorInterface;

/**
 * Laminas Validator Trait.
 *
 * Provides convenience helpers for building Laminas validator chains.
 * Requires laminas/laminas-validator package.
 */
trait LaminasValidatorTrait
{
    protected function createChain(): ValidatorChain
    {
        return new ValidatorChain();
    }

    protected function attachValidator(
        ValidatorChain $chain,
        ValidatorInterface $validator,
        ?string $message = null,
        ?bool $breakOnFailure = null,
        ?int $priority = null,
    ): void {
        if ($message !== null) {
            $validator->setMessage($message);
        }

        $chain->attach(
            $validator,
            breakChainOnFailure: $breakOnFailure,
            priority: $priority,
        );
    }
}
