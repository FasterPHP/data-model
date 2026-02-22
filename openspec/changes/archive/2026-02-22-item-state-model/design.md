## Context

Item lifecycle is currently expressed through three independent signals:

- **`isTemp()`** (Item:63-78) — 15 lines of conditional logic checking whether the id data entry is empty, whether it's a Field instance with an empty value, or whether `originalValues` contains a null id. Called by `saveItem()`/`saveSet()` to decide INSERT vs UPDATE.
- **`$persisted`** (Item:30) — private bool, set by `clearOriginalValues()`, used only as a guard for `assignId()`. Irrelevant once the item has an id.
- **`isDirty()`** (Item:80-83) — checks `!empty($this->originalValues)`. Only meaningful for non-temp items (temp items are always inserted regardless).

Two internal methods are publicly accessible: `clearOriginalValues()` (on `ItemInterface`) and `assignId()` (on `Item`). Both are only legitimately called by Repository during save operations.

## Goals / Non-Goals

**Goals:**
- Replace the three signals with a single `$itemState` property and three constants
- Merge `clearOriginalValues()` and `assignId()` into one `markItemPersisted()` method
- Establish correct state at construction time so `isTemp()` becomes a trivial comparison
- Preserve full item state through PHP serialisation round-trips
- Eliminate unnecessary Set creation when loading a single item
- Reduce serialisation duplication (`__toString` delegates to `jsonSerialize`)

**Non-Goals:**
- Preventing all possible misuse of internal methods — the goal is to make misuse unlikely, not impossible
- Changing the `originalValues` tracking mechanism itself — it stays as-is for field-level change tracking
- Modifying `jsonSerialize()` or `__toString()` output format — these remain clean field-value representations

## Decisions

### 1. Three constants on Item, private `$itemState` property

**Choice:** Define three private (or protected) constants and a private property:

```php
private const ITEM_STATE_TEMP = 'temp';
private const ITEM_STATE_CURRENT = 'current';
private const ITEM_STATE_MODIFIED = 'modified';
private string $itemState;
```

**Why private constants?** These are implementation details. No external code should branch on item state directly — they use `isTemp()` and `isDirty()` instead. Protected would allow subclass inspection, but there's no current need for that.

**Why string values?** Readable in debugging. Integer enums would be marginally faster but strings are clearer during `var_dump` / debugging.

### 2. Constructor `$isTemp` parameter determines initial state

**Choice:** Add `bool $isTemp = true` as a second constructor parameter. It sets `$itemState` and is not stored separately.

```php
public function __construct(array $data = [], bool $isTemp = true)
{
    $this->data = $data;
    $this->itemState = $isTemp ? self::ITEM_STATE_TEMP : self::ITEM_STATE_CURRENT;
}
```

**Why a constructor parameter rather than a static factory?** Minimal API surface change. The default (`true`) preserves backward compatibility for user-created items. Only Repository and Set need to pass `false`.

### 3. Constructor guard: temp items must not have an id

**Choice:** When `$isTemp` is `true` (the default), throw if the data array contains a non-empty id value.

```php
if ($isTemp && !empty($data[static::ID_INTERNAL])) {
    throw new Exception("Cannot construct a temporary item with an id value");
}
```

**Why?** A temp item with an id is an invalid state. This catches `new FooItem(['id' => 5])` immediately rather than causing confusing behaviour later during save.

**Why not also guard the reverse (non-temp without id)?** A non-temp item always comes from DB data passed through the constructor. The id is in the raw data array but hasn't been materialised into a Field yet (lazy initialisation). The data will always include the id from the SELECT — enforcing this in the constructor would add overhead for no practical benefit since the Repository and Set always include the id column.

### 4. `markItemPersisted(mixed $id = null)` replaces two methods

**Choice:** A single method that:
1. If `$id` is provided, require state is TEMP and set the id field value
2. Clear `originalValues`
3. Set state to CURRENT

```php
public function markItemPersisted(mixed $id = null): void
{
    if ($id !== null) {
        if ($this->itemState !== self::ITEM_STATE_TEMP) {
            throw new Exception("Cannot set id on a non-temporary item");
        }
        $this->getField(static::ID_INTERNAL)->setValue($id);
    }
    $this->originalValues = [];
    $this->itemState = self::ITEM_STATE_CURRENT;
}
```

**Why merge?** The two-step `clearOriginalValues()` → `assignId()` required specific ordering and exposed two internal methods. A single method with an optional id parameter eliminates the ordering concern and reduces the public surface.

**Why not on ItemInterface?** This is a Repository-internal operation. Users interacting via `ItemInterface` have no legitimate reason to call it.

**Return type:** `void` instead of `static`. The fluent return on `clearOriginalValues()` was unused (Repository never chained it). `void` signals this is a command, not a builder.

### 5. State transitions in `setValue()`

**Choice:** After the existing `originalValues` tracking logic in `setValue()`, add state transitions:

```php
if ($this->itemState === self::ITEM_STATE_CURRENT && !empty($this->originalValues)) {
    $this->itemState = self::ITEM_STATE_MODIFIED;
} elseif ($this->itemState === self::ITEM_STATE_MODIFIED && empty($this->originalValues)) {
    $this->itemState = self::ITEM_STATE_CURRENT;
}
```

**Why bidirectional?** The existing revert logic (Item:185-186) already removes `originalValues` entries when a value is set back to original. If all changes are reverted, the item should return to CURRENT, not stay MODIFIED. This keeps `isDirty()` (now `$itemState === MODIFIED`) consistent with the actual `originalValues` content.

**Why no TEMP → MODIFIED transition?** A temp item that has values set is still temp — it will be inserted, not updated. The state model deliberately keeps temp items in TEMP regardless of field changes.

### 6. `__serialize()` / `__unserialize()` full state round-trip

**Choice:** `__serialize()` returns a structured array with all state needed for faithful restoration:

```php
public function __serialize(): array
{
    return [
        'values' => [static::ID_INTERNAL => $this->getId()] + $this->getValues(),
        'originalValues' => $this->originalValues,
        'toDelete' => $this->toDelete,
    ];
}
```

`__unserialize()` restores everything and derives `$itemState`:

```php
public function __unserialize(array $serialized): void
{
    $this->data = $serialized['values'];
    $this->originalValues = $serialized['originalValues'];
    $this->toDelete = $serialized['toDelete'];

    $hasId = !empty($this->data[static::ID_INTERNAL]);
    if (!$hasId) {
        $this->itemState = self::ITEM_STATE_TEMP;
    } elseif (!empty($this->originalValues)) {
        $this->itemState = self::ITEM_STATE_MODIFIED;
    } else {
        $this->itemState = self::ITEM_STATE_CURRENT;
    }
}
```

**Why derive state rather than serialise it?** The state is fully determined by the data. Serialising it would be redundant and create a risk of inconsistency if the serialised data is tampered with.

**Alternative considered: serialise raw `$this->data` array.** This would include Field instances, which are complex objects. Serialising processed values (via `getValues()`) produces a clean, portable representation that reconstructs fields lazily on access — same as the current constructor-based hydration path.

### 7. `__toString()` delegates to `jsonSerialize()`

**Choice:** `__toString()` becomes `json_encode($this->jsonSerialize())` instead of duplicating the values-gathering expression. This eliminates the three-way duplication of `[static::ID_INTERNAL => $this->getId()] + $this->getValues()` — `jsonSerialize()` becomes the single source of truth for the external representation.

### 8. `Set::getItem()` passes `isTemp: false`

**Choice:** Update the lazy hydration in `Set::getItem()`:

```php
$this->data[$offset] = new $this->itemClassName($this->data[$offset], isTemp: false);
```

**Why here?** Set data always comes from database results. Items hydrated from raw row arrays are never temp. `Set::createItem()` (line 53-58) continues to use the default `true` since it creates new items for insertion.

### 9. `Repository::getItemWithParams()` bypasses Set

**Choice:** Fetch data directly and create a single Item:

```php
public function getItemWithParams(array $params, array $types = []): ?ItemInterface
{
    $data = $this->getDataWithParams($params, $types);
    if (empty($data)) {
        return null;
    }
    return $this->createItem($data[0]);
}
```

With `createItem()` updated to pass `isTemp: false`:

```php
protected function createItem(array $data): Item
{
    return new $this->itemClassName($data, isTemp: false);
}
```

**Why?** Currently `getItemWithParams()` creates a full Set (instantiating the Set class, storing all result rows) just to return `$set[0]`. For single-item lookups this is unnecessary overhead.

**Note on `createItem()`:** This factory method changes from creating temp items to creating loaded items. This is correct — `createItem()` is only called with DB row data. User code that needs a new temp item uses `new FooItem()` directly or `Set::createItem()`.

### 10. Repository persistence flow

**Choice:** Replace the two-step calls in `insertItem()` and `updateItem()`:

```php
// insertItem — after executing INSERT:
if (empty($item->getId())) {
    $newId = $this->getPdo()->lastInsertId();
    $item->markItemPersisted($newId ?: null);
} else {
    $item->markItemPersisted();
}

// updateItem — after executing UPDATE:
$item->markItemPersisted();
```

The ordering concern from the protect-assign-id change is eliminated — there's no separate method to call in the right sequence.

## Risks / Trade-offs

**Breaking serialisation format** → Existing serialised items (caches, sessions) won't unserialise correctly. Mitigation: pre-1.0 library, acceptable to break.

**`createItem()` semantics change** → The protected factory now creates non-temp items. Any subclass overriding `createItem()` would need to account for this. Mitigation: the method was always used for DB hydration, never for creating new temp items.

**`isDirty()` returns false for temp items with set values** → Previously, setting a value on a temp item populated `originalValues`, making `isDirty()` return true. Now temp items are always TEMP (never MODIFIED), so `isDirty()` returns false. Mitigation: `isDirty()` was never meaningful for temp items — the save logic checks `isTemp()` first and always inserts.

**Bidirectional CURRENT ↔ MODIFIED transition** → `setValue()` now has state management responsibility. Mitigation: the transition logic is simple (two comparisons) and only fires when the existing `originalValues` tracking already runs.
