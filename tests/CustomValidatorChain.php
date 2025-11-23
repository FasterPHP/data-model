<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Custom validator chain that mimics the interface expected by Item::validate().
 */
class CustomValidatorChain
{
    private array $configs;
    private array $messages = [];

    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    public function isValid($value): bool
    {
        $this->messages = [];

        foreach ($this->configs as $config) {
            if (isset($config['rule']) && $config['rule'] === 'not_test') {
                if ($value === 'test') {
                    $this->messages[] = "Custom validation failed for: {$value}";
                    return false;
                }
            }
        }

        return true;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
