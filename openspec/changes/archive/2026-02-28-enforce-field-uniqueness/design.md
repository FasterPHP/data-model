## Context

`Item::getField()` resolves a field's class name and readonly status via a null-coalesce chain across four const arrays, followed by a separate three-way `isset()` check for `$isReadonly`. This performs up to 7 array lookups per field resolution and has no mechanism to detect a field defined in multiple arrays. The id field migration guard only checks FIELDS.

The field resolution block (after the cache guard at line 226) only executes once per field per instance — subsequent calls return the cached `Field\Base` object. This means any per-field validation added here has bounded cost.

## Goals / Non-Goals

**Goals:**
- Detect and throw when a field is defined in more than one of FIELDS, FIELDS_READONLY, FIELDS_EXTERNAL, FIELDS_AGGREGATE
- Detect and throw when the id field is declared in any of the four arrays (not just FIELDS)
- Reduce total array lookups from 7 to 5 per field resolution
- Keep the change contained to the field resolution block in `getField()`

**Non-Goals:**
- Whole-class validation at construction or boot time — overlap is a class definition error best caught by unit tests; the `getField()` check is a runtime safety net
- Changing field resolution semantics — fields still resolve the same way for correctly-defined subclasses

## Decisions

### 1. Four boolean variables + arithmetic overlap detection

Replace the null-coalesce chain and separate `$isReadonly` derivation with four named booleans:

```php
$inFields    = isset(static::FIELDS[$fieldName]);
$inReadonly  = isset(static::FIELDS_READONLY[$fieldName]);
$inExternal  = isset(static::FIELDS_EXTERNAL[$fieldName]);
$inAggregate = isset(static::FIELDS_AGGREGATE[$fieldName]);

if ($inFields + $inReadonly + $inExternal + $inAggregate > 1) {
    throw new Exception("Field '$fieldName' is defined in multiple field arrays");
}
```

**Why over bitwise flags:** Same lookup count, but boolean addition is immediately readable — no need to understand bit manipulation or magic numbers. The overlap check (`> 1`) is self-documenting.

### 2. `match` expression for class name resolution with `$isIdField` arm

```php
$fieldClassName = match (true) {
    $isIdField   => static::ID_TYPE,
    $inFields    => static::FIELDS[$fieldName],
    $inReadonly  => static::FIELDS_READONLY[$fieldName],
    $inExternal  => static::FIELDS_EXTERNAL[$fieldName],
    $inAggregate => static::FIELDS_AGGREGATE[$fieldName],
};
$isReadonly = $inReadonly || $inExternal || $inAggregate;
```

This unifies id field resolution and regular field resolution into one expression. The id migration guard becomes a simple check before the match:

```php
if ($isIdField && $inFields + $inReadonly + $inExternal + $inAggregate > 0) {
    throw new Exception("Do not declare '" . static::ID_INTERNAL . "' in field arrays — ...");
}
```

**Why:** Replaces three separate code paths (migration guard if-block, null-coalesce chain, isset-based `$isReadonly`) with a single cohesive flow. The `match` expression is exhaustive and makes each resolution path explicit.

### 3. Existing "field not defined" check remains as-is

The guard at lines 217-225 that throws when a non-id field isn't in any array stays unchanged. It runs before the new code and shortcuts correctly.

## Risks / Trade-offs

- **Marginal overhead for overlapping definitions** → Only manifests for incorrectly-defined subclasses, which will immediately throw. Correctly-defined subclasses see fewer lookups than before.
- **`match` UnhandledMatchError if no arm matches** → Cannot happen: non-id fields without a definition are caught by the existing guard above, and id fields match the `$isIdField` arm.
