# ADR 0001: Replace constant-based validators with validate methods

## Status

Accepted

## Context

Validation in `faster-php/data-model` is opt-in via two traits: `ValidatableTrait` (orchestration) and `LaminasValidatorTrait` (Laminas-specific chain building). Validators were defined as nested arrays in a `VALIDATORS` class constant on each Item subclass:

```php
public const VALIDATORS = [
    'name' => [
        ['class' => StringLength::class, 'options' => ['min' => 2, 'max' => 60], 'message' => '...'],
    ],
];
```

This approach had two problems:

1. **No coverage visibility.** Constants are data, not executable code. Coverage tools cannot report whether each field's validators are exercised by tests. There is no way to know at a glance which validators are tested.
2. **Complex config parsing.** `LaminasValidatorTrait::addValidator()` handled 6+ config keys (`class`, `options`, `break`, `priority`, `skipIfEmpty`, `message`) plus a special case for `Callback` validators. This complexity lived in the trait rather than being explicit in each Item.

The original rationale for constants was early parsing benefits under Swoole/FrankenPHP worker mode. This does not hold up: OPcache compiles both constants and method bodies once, and both persist identically across requests in worker mode.

## Decision

Replace the `VALIDATORS` constant convention with `validate{FieldName}()` instance methods discovered by convention.

**ValidatableTrait** iterates `static::FIELDS` keys and calls `$this->validateName()`, `$this->validateAge()`, etc. via `method_exists()`. Fields without a matching method are silently skipped.

**LaminasValidatorTrait** no longer couples to `ValidatableTrait`. It provides two convenience helpers:
- `createChain(): ValidatorChain`
- `attachValidator(ValidatorChain $chain, ValidatorInterface $validator, ...): void`

Each `validate{FieldName}()` method is a protected instance method that builds and returns a `ValidatorChain`. It has full access to `$this`.

Example:

```php
class UserItem extends Item
{
    use ValidatableTrait;
    use LaminasValidatorTrait;

    protected function validateName(): ValidatorChain
    {
        $chain = $this->createChain();
        $this->attachValidator($chain, new StringLength(['min' => 2, 'max' => 60]), message: 'Name must be 2-60 chars');
        return $chain;
    }
}
```

## Consequences

### Positive

- Each field's validation logic appears individually in coverage reports
- Full `$this` access without workarounds (no `skipIfEmpty` config key, no Callback special case)
- Simpler trait code — traits orchestrate discovery/execution, not config parsing
- IDE support: go-to-definition, find-usages, refactoring all work on methods
- `ValidatableTrait` is usable without `LaminasValidatorTrait` by building chains directly

### Negative

- **Breaking change** for any code defining a `VALIDATORS` constant. Migration is mechanical: each array entry becomes a method.
- More boilerplate per field (a method vs. an array entry). Mitigated by convenience helpers keeping methods to 3-5 lines.
- Validators are scattered across methods rather than visible in one constant block. Mitigated by the `validate*` naming convention.
