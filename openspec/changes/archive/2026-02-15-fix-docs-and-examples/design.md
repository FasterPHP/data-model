## Context

The validation system was refactored from a `VALIDATORS` constant-based approach to `validate{FieldName}()` method discovery (commit `a179994`). The README and examples 01, 03, and 06 still reference the old approach. The README also has pre-existing errors unrelated to the validation refactor (wrong class for `DB_NAME`/`TABLE_NAME`, wrong field constant for joins, missing `ID_FIELD`).

The existing test model `tests/TestModel/ValidItem.php` and the spec at `openspec/specs/method-based-validation/spec.md` serve as the source of truth for how validation works now.

## Goals / Non-Goals

**Goals:**
- Every code snippet in README.md accurately reflects current API behavior
- Examples 01, 03, and 06 are runnable without fatal errors (given appropriate dependencies)
- New users following the docs can successfully use the validation system
- The custom validator pattern (non-Laminas) is clearly documented with the actual extension point

**Non-Goals:**
- Changing any source code in `src/` — this is docs-only
- Modifying examples 02, 04, or 05 (already correct)
- Rewriting the README structure or tone
- Creating framework-specific traits (Symfony/Laravel adapters are simple enough to inline in examples)

## Decisions

### 1. README Quick Start: Show magic getters/setters as primary, explicit methods as optional

**Decision**: Show the Item class without explicit getters/setters (relying on `__call`), with a note explaining that explicit methods can be added for IDE autocompletion.

**Rationale**: The examples already rely on magic methods exclusively. Showing explicit methods first creates the false impression they're required, and bloats the Quick Start. A brief note covers the IDE/static-analysis use case.

**Alternative considered**: Keep explicit methods as the primary pattern. Rejected because it misrepresents how the library is actually used (all examples use magic methods).

### 2. README Quick Start: Move DB_NAME/TABLE_NAME to Repository, add ID_FIELD

**Decision**: The Quick Start Item class will show only `ID_FIELD`, `FIELDS`, and optionally `DEFAULTS`. The Repository class will show `DB_NAME` and `TABLE_NAME`.

**Rationale**: Matches actual class definitions — `Item` has no `DB_NAME`/`TABLE_NAME` constants, and `Repository` does.

### 3. README Joins: Replace FIELDS_READONLY with FIELDS_EXTERNAL

**Decision**: Use `FIELDS_EXTERNAL` in the joins example.

**Rationale**: `FIELDS_READONLY` fields are included in the default `SELECT` via `Repository::getFieldList()`, which would attempt to select the join field from the base table. `FIELDS_EXTERNAL` fields are excluded from the default `SELECT` and must be added via `getSelectClause()` override, which is exactly the join pattern.

### 4. Validation examples: Model after tests/TestModel/ValidItem.php

**Decision**: Use the existing test model as the reference pattern for all validation examples. Each example Item will:
- `use ValidatableTrait` and `use LaminasValidatorTrait`
- Define `protected function validate{FieldName}(): ValidatorChain` methods
- Use `$this->createChain()` and `$this->attachValidator()` helpers

**Rationale**: The test model is the canonical, tested implementation of the new validation API. Mirroring it ensures examples stay consistent with the test suite.

### 5. Example 06 custom validators: Show validate{FieldName}() returning a custom chain object

**Decision**: The `CustomUserItem` in example 06 will define `validate{FieldName}()` methods that return a `CustomValidatorChain` object (implementing `isValid($value)` and `getMessages()`), without `LaminasValidatorTrait`. Remove `buildValidatorChain()` entirely.

**Rationale**: `ValidatableTrait::validate()` calls `$this->$method()` and then `$chain->isValid($value)` / `$chain->getMessages()`. It has no return type constraint, so any object matching that duck-type interface works. This is the actual extension point.

### 6. Example 06 StandardUserItem: Add traits and methods

**Decision**: `StandardUserItem` will use `ValidatableTrait` + `LaminasValidatorTrait` and define `validateName()` / `validateEmail()` methods, matching the same pattern as examples 01 and 03.

**Rationale**: Currently missing both traits and would fatal error on `isValid()`.

### 7. Add framework-specific examples for Symfony and Laravel

**Decision**: Create `examples/06-symfony-validation.php` and `examples/07-laravel-validation.php` with concrete adapter classes. Rename existing `06-custom-validators.php` to `08-custom-validators.php` as the generic fallback. Keep the README custom validators section brief and reference the examples.

**Rationale**: Full adapter examples in the README would make it too long. The examples directory is the right place for complete working code. Framework-specific examples come before the generic example since they're the common case.

**Alternative considered**: Ship adapter classes and traits in `src/Validation/` for Symfony and Laravel. Rejected because:
- The adapters are ~20 lines each and trivial to write
- `LaminasValidatorTrait` only works because `ValidatorChain` already satisfies the duck-type contract natively — Symfony/Laravel don't, so the trait would just be `new Adapter(...)` which adds no value
- Shipping adapters adds optional dependencies and maintenance burden for framework API changes

### 8. Laravel example: note about service container coupling

**Decision**: The Laravel example will use `Illuminate\Validation\Factory` directly (not the Facade), with a note that in a real Laravel app the factory is available via DI. The example will bootstrap a minimal translator for standalone demonstration.

**Rationale**: `Validator::make()` requires the service container. Showing the factory directly makes the adapter's mechanics clear and avoids a facade dependency that wouldn't work in the example context.

## Risks / Trade-offs

- **Risk**: Examples become slightly more verbose (method definitions vs. a constant array) → Acceptable; the methods are clearer and match the actual API
- **Risk**: Example 08's `CustomValidatorChain` relies on duck-typing (`isValid`/`getMessages`) rather than a formal interface → This matches the actual `ValidatableTrait` behavior; documenting it honestly is better than pretending an interface exists
- **Risk**: Symfony/Laravel examples can't be run without their respective packages installed → Acceptable; the examples serve as copy-paste templates. A note at the top of each file lists the required `composer require` command
