## 1. Item state constants and constructor

- [x] 1.1 Add private constants `ITEM_STATE_TEMP`, `ITEM_STATE_CURRENT`, `ITEM_STATE_MODIFIED` and private `string $itemState` property to Item
- [x] 1.2 Add `bool $isTemp = true` second parameter to Item constructor; set `$itemState` based on its value
- [x] 1.3 Add constructor guard: throw if `$isTemp` is `true` and data contains a non-empty `ID_INTERNAL` key
- [x] 1.4 Remove the `$persisted` property from Item

## 2. State-derived methods and transitions

- [x] 2.1 Replace `isTemp()` body with `return $this->itemState === self::ITEM_STATE_TEMP`
- [x] 2.2 Replace `isDirty()` body with `return $this->itemState === self::ITEM_STATE_MODIFIED`
- [x] 2.3 Add state transitions in `setValue()`: CURRENT → MODIFIED when `originalValues` becomes non-empty, MODIFIED → CURRENT when `originalValues` becomes empty

## 3. markItemPersisted method

- [x] 3.1 Add `markItemPersisted(mixed $id = null): void` method to Item — if `$id` is non-null, require TEMP state and set id field; clear `originalValues`; set state to CURRENT
- [x] 3.2 Remove `clearOriginalValues()` method from Item
- [x] 3.3 Remove `assignId()` method from Item
- [x] 3.4 Remove `clearOriginalValues()` from ItemInterface

## 4. Serialisation

- [x] 4.1 Update `__serialize()` to return structured array with `values`, `originalValues`, and `toDelete` keys
- [x] 4.2 Update `__unserialize()` to restore `data`, `originalValues`, `toDelete`, and derive `itemState` from the data
- [x] 4.3 Update `__toString()` to delegate to `json_encode($this->jsonSerialize())`

## 5. Set integration

- [x] 5.1 Update `Set::getItem()` to pass `isTemp: false` when constructing Item from raw array data

## 6. Repository integration

- [x] 6.1 Update `Repository::createItem()` to pass `isTemp: false` to Item constructor
- [x] 6.2 Update `Repository::getItemWithParams()` to use `getDataWithParams()` and `createItem()` directly, bypassing Set
- [x] 6.3 Update `Repository::insertItem()` to call `markItemPersisted($id)` instead of `clearOriginalValues()` + `assignId()`
- [x] 6.4 Update `Repository::updateItem()` to call `markItemPersisted()` instead of `clearOriginalValues()`

## 7. Tests

- [x] 7.1 Add tests for Item state constants (private visibility via reflection)
- [x] 7.2 Add tests for constructor `$isTemp` parameter: default creates temp, `false` creates current
- [x] 7.3 Add tests for constructor guard: temp item with id throws, non-temp with id allowed
- [x] 7.4 Add tests for `isTemp()` state derivation: new item, loaded item, after `markItemPersisted`
- [x] 7.5 Add tests for `isDirty()` state derivation: new item, loaded item, modified item, temp item with set values
- [x] 7.6 Add tests for `setValue()` state transitions: CURRENT → MODIFIED, MODIFIED → CURRENT on revert, TEMP stays TEMP
- [x] 7.7 Add tests for `markItemPersisted()`: temp with id, temp without id, modified item, throws on non-temp with id, not on ItemInterface, returns void
- [x] 7.8 Add tests for `__serialize()`/`__unserialize()` round-trip: current item, modified item, temp item, item marked for deletion
- [x] 7.9 Add tests for `__toString()` delegation to `jsonSerialize()`
- [x] 7.10 Update existing tests that reference `clearOriginalValues()` or `assignId()` to use `markItemPersisted()`
- [x] 7.11 Verify `Set::getItem()` produces non-temp items and `Set::createItem()` produces temp items
- [x] 7.12 Verify `Repository::getItemWithParams()` returns correct item without Set instantiation
- [x] 7.13 Run full test suite and fix any failures
