## Context

`Item::setValue()` (line 177–186 in `src/Item.php`) guards against setting fields declared in `FIELDS_READONLY`, `FIELDS_EXTERNAL`, and `FIELDS_AGGREGATE`. The guard is unconditional — it does not consider item state. This means a newly constructed temp item cannot have its readonly fields populated via setters, even though the item has never been persisted.

The item state model already distinguishes TEMP, CURRENT, and MODIFIED states (see `item-state-model` spec). Field construction in `getField()` already notes "bypasses readonly check" when setting initial values from raw data arrays (line 258). The gap is that `setValue()` (the setter path) has no equivalent bypass for temp items.

Downstream impact: any Item subclass that declares write-once fields (e.g. foreign keys, tokens, expiry dates) in `FIELDS_READONLY` cannot have those fields populated via setters on a newly constructed item — the setters throw even though the item has never been persisted.

## Goals / Non-Goals

**Goals:**
- Allow `FIELDS_READONLY` fields to be set via setters on temp (new, unpersisted) items.
- Preserve the immutability guarantee for readonly fields on persisted (CURRENT/MODIFIED) items.
- Keep `FIELDS_EXTERNAL` and `FIELDS_AGGREGATE` unconditionally immutable (they represent computed/joined data).

**Non-Goals:**
- Changing `FIELDS_READONLY` semantics for hydration from raw data (already works via `getField()`).
- Introducing a new field category (e.g. `FIELDS_WRITE_ONCE`) — the existing `FIELDS_READONLY` already has the right semantic, it's just enforced too aggressively.
- Modifying downstream Item subclasses — they are correct as-is once the guard is relaxed.

## Decisions

### 1. Relax the guard in `setValue()` based on item state

Change the conditional in `setValue()` from:
```php
if (
    isset(static::FIELDS_READONLY[$fieldName])
    || isset(static::FIELDS_EXTERNAL[$fieldName])
    || isset(static::FIELDS_AGGREGATE[$fieldName])
) {
```
to:
```php
if (
    isset(static::FIELDS_EXTERNAL[$fieldName])
    || isset(static::FIELDS_AGGREGATE[$fieldName])
    || (isset(static::FIELDS_READONLY[$fieldName]) && !$this->isTemp())
) {
```

**Rationale**: This is a single-line change that leverages the existing `isTemp()` method. External and aggregate fields remain always-rejected since they are never user-supplied. The error message stays the same for the rejection cases.

**Alternative considered**: Adding a separate `setInitialValue()` method — rejected because it would require downstream callers to know whether they're dealing with a new or existing item, defeating the purpose of the state model.

### 2. Update existing tests, not just add new ones

Any existing unit tests that assert `setValue()` throws on readonly fields for temp items will now need to assert success instead. New tests should cover:
- Setting a readonly field on a temp item succeeds
- Setting a readonly field on a CURRENT item still throws
- Setting a readonly field on a MODIFIED item still throws

## Risks / Trade-offs

- **[Semantic shift]** Code that previously relied on readonly fields being completely immutable via setters may break. → Mitigation: This is documented as a **BREAKING** change in the proposal. The previous behaviour was a bug, not a feature — the field construction path already bypasses the readonly check for raw data.
- **[State leakage on temp items]** A temp item could have readonly fields set multiple times before persistence. → Mitigation: This is acceptable and consistent with how regular `FIELDS` behave on temp items. Once persisted, immutability kicks in.
