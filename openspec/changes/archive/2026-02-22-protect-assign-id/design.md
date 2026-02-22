## Context

The `assignId()` method was introduced in the `implicit-id-field` change to let Repository write the auto-increment value after INSERT, bypassing the `__call` guard on `setId()`. It is currently public on both `Item` and `ItemInterface`, with only an `@internal` docblock as a signal not to use it.

The method must remain public because Repository is not a subclass of Item — PHP has no package-private visibility. The goal is to add runtime protection so the method can only succeed in the correct context (after a persistence operation on a temp item).

Current `insertItem()` flow:
```
INSERT → assignId($newId) → clearOriginalValues()
```

## Goals / Non-Goals

**Goals:**
- Prevent `assignId()` from being used as a backdoor for `setId()`
- Prevent premature calls (before the item has been saved)
- Prevent calls on loaded items (already have an id)
- Remove `assignId()` from `ItemInterface` (not part of consumer contract)
- Keep the Repository INSERT flow working

**Non-Goals:**
- Making `assignId()` private/protected (impossible without changing class hierarchy)
- Preventing deliberately malicious misuse by someone who studies the internals

## Decisions

### 1. Private `$persisted` flag on Item

**Choice:** Add `private bool $persisted = false` to Item. This flag tracks whether the item has been through a persistence operation. It is private — no getter, no external way to inspect or set it.

**Why private, not protected?** There is no reason for subclasses to access this flag. Keeping it private minimises the surface area.

### 2. `clearOriginalValues()` sets the flag

**Choice:** `clearOriginalValues()` sets `$this->persisted = true`. This method is already called by Repository after every INSERT and UPDATE, making it the natural gate.

**Why not a new method?** Adding a dedicated `beginPersist()` / `endPersist()` pair would increase the public API surface with the same discoverability problem. `clearOriginalValues()` is already "internal-facing" and always called during the save flow.

**Side effect on UPDATE:** After an UPDATE, `persisted` is also set to true. This is harmless because `isTemp()` is false for updated items, so the `assignId()` guard still blocks.

### 3. `assignId()` guards: `$persisted && isTemp()`

**Choice:** `assignId()` throws unless both conditions are met:
- `$this->persisted` is true (item has been through a save operation)
- `$this->isTemp()` is true (item does not yet have an id)

This creates a narrow window: the method only succeeds between `clearOriginalValues()` and the id actually being set.

**Error message:** Same as `setId()`: "Cannot set id directly; the id field is managed automatically" — a consumer hitting this gets the same guidance regardless of which method they tried.

### 4. Reorder Repository `insertItem()` flow

**Choice:** Change from `assignId → clearOriginalValues` to `clearOriginalValues → assignId`.

New flow:
```
INSERT → clearOriginalValues() → assignId($newId)
```

This ensures `$persisted` is true before `assignId()` is called. The end state is identical: item has an id, `isTemp()` is false, `originalValues` is empty.

### 5. Remove `assignId()` from `ItemInterface`

**Choice:** Remove the method from the interface. `insertItem()` takes `Item` (the concrete class), not `ItemInterface`, so it can still call the method.

Consumers coding to the interface — which is the encouraged pattern — will not see `assignId()` at all.

## Risks / Trade-offs

**`clearOriginalValues()` gains a side effect** → Setting `$persisted = true` is a minor coupling between two concerns. Acceptable because these always occur together during save, and the flag is invisible (private, no getter).

**Someone calls `clearOriginalValues()` then `assignId()` on a temp item** → This requires deliberately chaining two internal methods in the right order. It's not accidental misuse — it's intentional circumvention, which is a non-goal to prevent.

**Reordering `insertItem()` calls** → No functional difference. `clearOriginalValues()` clears the change tracker; `assignId()` writes the id via `Field::setValue()` (not `Item::setValue()`), so it doesn't populate `originalValues`. End state is the same.
