## Why

`Item::setValue()` unconditionally rejects setting `FIELDS_READONLY` fields, even on new (temp) items that have never been persisted. This prevents downstream packages from populating write-once fields (e.g. foreign keys, tokens) on newly constructed items — fields that are logically "set once at creation, immutable after persistence." The semantic intent of `FIELDS_READONLY` is write-once, not write-never.

## What Changes

- **BREAKING**: `Item::setValue()` will allow setting `FIELDS_READONLY` fields when the item is in TEMP state (i.e. `isTemp()` returns `true`). Once the item is persisted (CURRENT or MODIFIED state), readonly fields remain immutable as before.
- `FIELDS_EXTERNAL` and `FIELDS_AGGREGATE` fields remain unconditionally rejected by `setValue()` since they are computed/joined values that should never be set directly.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `item-state-model`: The readonly guard in `setValue()` becomes state-aware — temp items can set `FIELDS_READONLY` fields, persisted items cannot.

## Impact

- **Item::setValue()**: Single conditional change in the readonly guard.
- **Downstream packages**: Any items using `FIELDS_READONLY` for write-once fields (e.g. foreign keys, tokens) will work correctly without workarounds.
- **Existing tests**: Any tests asserting that `setValue()` throws on readonly fields for temp items will need updating to reflect the new behaviour.
- **No migration needed**: This is a relaxation of a constraint, not a tightening. Existing persisted-item readonly enforcement is unchanged.
