## Why

The Item lifecycle currently relies on three loosely coupled signals — `isTemp()` (derived from complex id-presence logic), `$persisted` (a private flag set by `clearOriginalValues()`), and `isDirty()` (derived from `originalValues`). These overlap, interact in non-obvious ways, and expose internal methods (`clearOriginalValues`, `assignId`) that users can misuse. A unified state model simplifies reasoning, removes the complex `isTemp()` logic, and merges two internal methods into one with clearer intent.

## What Changes

- **BREAKING**: Replace `clearOriginalValues()` with `markItemPersisted(mixed $id = null): void` on Item. Remove `clearOriginalValues()` from `ItemInterface`.
- **BREAKING**: Remove the standalone `assignId()` method — its functionality is absorbed into `markItemPersisted($id)`.
- Introduce a private `$itemState` property with three constants (`ITEM_STATE_TEMP`, `ITEM_STATE_CURRENT`, `ITEM_STATE_MODIFIED`) replacing the separate `$persisted` flag.
- Add an `bool $isTemp = true` second parameter to the Item constructor. When `false`, initial state is CURRENT; when `true` (default), initial state is TEMP. The parameter is not stored — it only determines the initial state.
- Guard against invalid construction: when `$isTemp` is `true` (default), throw if an id value is provided in the data array.
- Simplify `isTemp()` from 15 lines of conditional logic to a single state comparison.
- Derive `isDirty()` from `$itemState === ITEM_STATE_MODIFIED` instead of checking `originalValues` directly.
- Transition CURRENT → MODIFIED in `setValue()` when a field changes; transition back to CURRENT when all changes are reverted (leveraging existing revert logic).
- **BREAKING**: Update `__serialize()` to include `originalValues` and `toDelete` alongside field data, enabling faithful state round-trips. `jsonSerialize()` and `__toString()` remain unchanged (clean field values for external representation).
- Update `__unserialize()` to restore the full item state — `originalValues`, `toDelete`, and derived `itemState` — from the richer serialised structure.
- Update `Set::getItem()` to pass `isTemp: false` when hydrating items from database rows.
- Optimise `Repository::getItemWithParams()` to create and hydrate a single Item directly from `getDataWithParams()`, bypassing Set creation.
- Update `Repository::insertItem()` and `updateItem()` to call `markItemPersisted()` instead of `clearOriginalValues()` + `assignId()`.

## Capabilities

### New Capabilities
- `item-state-model`: Unified three-state lifecycle model (TEMP, CURRENT, MODIFIED) for Item, including state constants, constructor `$isTemp` parameter, `markItemPersisted()` method, state-aware `isTemp()`/`isDirty()`, full-state serialisation round-trip, and Set/Repository integration.

### Modified Capabilities
- `implicit-id-field`: The `assignId()` method and `$persisted` flag requirements are replaced by the new state model and `markItemPersisted()`. The `clearOriginalValues()` references throughout are superseded. The Repository insert flow changes from `clearOriginalValues()` then `assignId()` to a single `markItemPersisted($id)` call.

## Impact

- **src/Item.php**: New `$itemState` property and constants, constructor signature change, `markItemPersisted()` replaces `clearOriginalValues()` and `assignId()`, simplified `isTemp()`/`isDirty()`, state transition in `setValue()`, `__serialize()`/`__unserialize()` updated for full state round-trip.
- **src/ItemInterface.php**: Remove `clearOriginalValues()`. No new methods added (markItemPersisted is internal).
- **src/Set.php**: `getItem()` passes `isTemp: false` to Item constructor.
- **src/Repository.php**: `insertItem()`/`updateItem()` call `markItemPersisted()`. `getItemWithParams()` bypasses Set creation. `createItem()` passes `isTemp: false` when used for hydration.
- **tests/**: Update all tests referencing `clearOriginalValues()`, `assignId()`, and `isTemp()` logic. Add tests for state transitions, constructor guard, and serialisation round-trip.
- **Breaking changes**: Any code calling `clearOriginalValues()` or `assignId()` directly must migrate to `markItemPersisted()`. Any code type-hinting `ItemInterface` and calling `clearOriginalValues()` will break. The `__serialize()` format changes, so previously serialised Item instances (e.g. in caches/sessions) will not unserialize correctly.
