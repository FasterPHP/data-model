## Context

Validation is currently opt-in via two traits:

- **`ValidatableTrait`** — provides `isValid()`, `validate()`, and `getValidationErrors()`. The `validate()` method iterates a `VALIDATORS` class constant (an associative array keyed by field name), passing each field's config array to `buildValidatorChain()`.
- **`LaminasValidatorTrait`** (uses `ValidatableTrait`) — implements `buildValidatorChain()` by parsing config arrays with keys like `class`, `options`, `break`, `priority`, `skipIfEmpty`, and `message`. Includes special handling for `Callback` validators (injecting `$this` as a callback option).

The config-parsing approach works but has grown complex (`addValidator()` handles 6+ config keys and a special case), and the constant-based definition means coverage tools cannot track which fields' validators are tested.

## Goals / Non-Goals

**Goals:**
- Each field's validation logic is a discrete method that appears in coverage reports
- Simpler trait code — the traits orchestrate discovery and execution, not config parsing
- Full `$this` access in validator methods without workarounds (no `skipIfEmpty` config key, no Callback special case)
- Clear migration path for consumers

**Non-Goals:**
- Changing the Laminas validator dependency or switching validator libraries
- Adding new validation capabilities (e.g. cross-field validation) — that can come later
- Changing the `isValid()` / `getValidationErrors()` public API on items

## Decisions

### 1. Method discovery via FIELDS constant iteration

**Decision**: Iterate `static::FIELDS` keys and check `method_exists($this, 'validate' . ucfirst($fieldName))` for each field.

**Alternative considered**: Use reflection to find all methods matching `validate*`. Rejected because it's slower, less explicit, and could match unrelated methods. Iterating known field names is predictable and avoids reflection overhead.

**Alternative considered**: A new `VALIDATED_FIELDS` constant listing which fields have validators. Rejected because it re-introduces the "data not code" problem and adds a second place to keep in sync.

### 2. Return type: `ValidatorChain`

**Decision**: Each `validate{FieldName}()` method returns a `Laminas\Validator\ValidatorChain`. The trait calls `$chain->isValid($value)` and `$chain->getMessages()` as before.

**Alternative considered**: Return `bool` and set errors directly. Rejected because it would couple the validation API to a specific error-reporting mechanism and lose the composability of validator chains.

**Alternative considered**: Return an array of validator configs (same structure as today). Rejected because it doesn't solve the coverage problem — the config would still need parsing.

### 3. ValidatableTrait becomes the sole discovery/execution trait

**Decision**: `ValidatableTrait::validate()` handles method discovery and execution. It no longer checks for a `VALIDATORS` constant. The `buildValidatorChain()` abstract method is removed.

`LaminasValidatorTrait` becomes a helper trait providing convenience methods for building chains (e.g. `createChain()`, `attachValidator()`), but is no longer required. An Item can use `ValidatableTrait` alone and build chains manually.

**Alternative considered**: Keep `buildValidatorChain()` as a hook. Rejected because the method-per-field approach makes a single chain-builder unnecessary — each field method is its own builder.

### 4. Convenience helpers on LaminasValidatorTrait

**Decision**: `LaminasValidatorTrait` provides:
- `createChain(): ValidatorChain` — returns a new empty chain
- `attachValidator(ValidatorChain $chain, ValidatorInterface $validator, ?string $message = null, ?bool $breakOnFailure = null, ?int $priority = null): void` — attaches a validator with optional overrides

These cover the most common patterns from the old config (`class`, `options`, `message`, `break`, `priority`) without requiring consumers to remember the Laminas API.

### 5. No backward compatibility shim for VALIDATORS constant

**Decision**: The `VALIDATORS` constant is no longer read. This is a clean break.

**Alternative considered**: A fallback that reads `VALIDATORS` if no `validate*` methods exist. Rejected because it doubles the code paths, makes testing harder, and delays migration. The package is pre-1.0 and the migration is mechanical.

## Risks / Trade-offs

- **Breaking change** → Mitigated by the package being pre-1.0. Migration is mechanical: each array entry becomes a method. The proposal documents the migration path.
- **More boilerplate per field** → Each field needs a method instead of an array entry. Mitigated by convenience helpers that keep methods concise (3-5 lines typical). The explicitness is the point — it enables coverage tracking and IDE support.
- **Discoverability** → With constants, all validators are visible in one block. With methods, they're scattered across the class. Mitigated by the naming convention making them easy to find (`validate*`), and IDEs can list methods matching a pattern.

## Open Questions

_(none — design is straightforward given the constraints)_
