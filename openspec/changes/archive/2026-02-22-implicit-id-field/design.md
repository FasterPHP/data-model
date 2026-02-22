## Context

The id field is currently declared by every Item subclass in `FIELDS`, making it appear user-writable. In reality it maps to an auto-incrementing database column (`ID_FIELD`) and is managed by the Repository after INSERT via `lastInsertId()`. The internal name is always `'id'` (`ID_INTERNAL`), while the database column name varies (e.g. `'userId'`). The `getFieldList()` method in Repository already handles the `SELECT userId AS id` mapping, and `getSqlValues(false)` silently excludes the null id on INSERT. But nothing in the API communicates that `setId()` shouldn't be called by users — doing so would produce a broken INSERT (writing to column `'id'` instead of the real column name).

Key existing mechanisms:
- `Item::__call()` dispatches `setId()` → `setValue('id', ...)` and `getId()` → `getFieldValue('id')`
- `Item::setValue()` checks `FIELDS_READONLY`, `FIELDS_EXTERNAL`, `FIELDS_AGGREGATE` — but not the id field
- `Item::getField()` already has special-case logic for the id field (lines 200-208): blocks DEFAULTS, defaults to null
- `Field\Base` has `setValueInternal()` which bypasses the readonly check — used by the constructor for initial values
- `Repository::insertItem()` calls `$item->setId($newId)` after INSERT
- `Repository::getFieldList()` already handles id specially: `table.userId AS id`

## Goals / Non-Goals

**Goals:**
- Make it impossible for users to accidentally call `setId()` — throw a clear exception
- Remove the requirement for subclasses to declare `'id'` in `FIELDS`
- Provide `ID_TYPE` so subclasses can specify the field type (default `Field\Integer::class`)
- Provide an internal `assignId()` method for Repository to write the auto-increment value
- Keep `getId()`, `isTemp()`, `isDirty()`, and all Repository operations working unchanged
- Keep `getFieldList()` working — it must include the id in SELECT even though it's not in FIELDS

**Non-Goals:**
- Composite primary keys
- User-settable primary keys (UUIDs as PK) — use auto-increment + separate unique field instead
- Changing the `ID_FIELD` / `ID_INTERNAL` constants or their semantics
- Modifying `Field\Base` internals

## Decisions

### 1. Id field is implicitly created by `getField()`, not declared in FIELDS

**Choice:** Remove `'id'` from FIELDS in all subclasses. The `getField()` method already has special handling for the id field — extend it to create the Field object using `ID_TYPE` when the field name matches `ID_INTERNAL`, even if it's not in any FIELDS constant.

**Why not FIELDS_READONLY?** Moving id to FIELDS_READONLY was considered. It would make `setValue()` throw, but it would also include the id in `getSqlValues()` (which iterates `FIELDS + FIELDS_READONLY`). That would produce broken INSERTs (column name `'id'` instead of `'userId'`). It would also require every subclass to declare the field in a different constant rather than removing it entirely. The implicit approach is cleaner.

**Why not a new FIELDS_AUTO constant?** Adding another field category increases complexity. The id is already unique — it has its own constants (`ID_FIELD`, `ID_INTERNAL`) and special handling throughout the codebase. Making it fully implicit acknowledges this reality rather than trying to fit it into the field-category system.

### 2. Throw on `setId()` in `__call()`

**Choice:** Add an early check in `__call()`: if the setter targets `ID_INTERNAL`, throw `Exception("Cannot set id directly; the id field is managed automatically")`.

**Why in `__call()` rather than `setValue()`?** The `setValue()` method is `protected` and used internally. Blocking it there would also block the internal `assignId()` flow. By blocking in `__call()` (the public entry point for magic setters), we protect users while keeping internal access available.

### 3. `assignId()` method on Item

**Choice:** Add a public `assignId(mixed $id): void` method to Item that writes the id value directly, bypassing the `__call` guard.

Implementation: call `getField(ID_INTERNAL)` to get/create the Field object, then use the Field's `setValueInternal()` method to bypass readonly. This requires either making `setValueInternal()` accessible or setting the value through the data array before the Field is instantiated.

Simpler approach: since Repository calls `assignId()` after INSERT (before the Field may have been instantiated as an object), we can write the raw scalar value directly into `$this->data[static::ID_INTERNAL]`. The lazy `getField()` method already handles raw scalars in `$this->data` — it creates the Field object on first access from whatever value is there. This avoids needing to change `Field\Base` visibility.

```php
public function assignId(mixed $id): void
{
    $field = $this->getField(static::ID_INTERNAL);
    // Use reflection or direct property access on the Field to bypass readonly.
    // Alternatively, store raw value and let lazy init handle it:
    $this->data[static::ID_INTERNAL] = $id;
}
```

Actually, if the Field object already exists in `$this->data` (e.g. `getId()` was called before `assignId()`), writing a raw scalar would break it. Safest approach: always go through the Field object. Since we're not putting id in any FIELDS_READONLY category, the Field won't be created with `$isReadonly = true`, so `Field::setValue()` will work. The `assignId` method can simply do:

```php
public function assignId(mixed $id): void
{
    $this->getField(static::ID_INTERNAL)->setValue($id);
}
```

This works because the id Field is created with `$isReadonly = false` (it's not in FIELDS_READONLY). The protection is only at the `__call()` level.

### 4. `getField()` recognises the id field without it being in FIELDS

**Choice:** Extend the field-existence check at the top of `getField()` to also accept `ID_INTERNAL`. When creating the Field object, use `static::ID_TYPE` as the class name and `$isReadonly = false`.

The existing special-case code for the id field (null default, blocking DEFAULTS) remains.

### 5. `getSqlValues()` and `getValues()` exclude the id field

**Choice:** No change needed — these methods iterate `FIELDS` (and `FIELDS_READONLY` for `getSqlValues`). Since id is no longer in any of these constants, it's automatically excluded. This is the correct behaviour: the id column name in the database is `ID_FIELD`, not `'id'`, so it should never appear in INSERT/UPDATE column lists.

### 6. `getFieldList()` in Repository must still include id in SELECT

**Choice:** `getFieldList()` currently iterates `FIELDS + FIELDS_READONLY` and applies the `ID_FIELD AS ID_INTERNAL` alias when it encounters the id. Since id will no longer be in FIELDS, we need to prepend the id column explicitly before iterating the other fields.

### 7. `jsonSerialize()`, `__serialize()`, `__toString()` should include id

**Choice:** These currently delegate to `getValues()`, which will no longer include id. Update them to prepend the id value: `['id' => $this->getId()] + $this->getValues()`. This ensures serialised output still contains the id, which consumers expect.

### 8. Error on `'id'` in FIELDS

**Choice:** Add a check in `getField()` (or the constructor): if `static::FIELDS` contains a key matching `ID_INTERNAL`, throw an exception with a clear migration message: *"Do not declare 'id' in FIELDS — it is managed automatically. Remove 'id' from FIELDS and optionally set ID_TYPE to specify the field type."*

This catches stale subclasses that haven't been updated and makes the migration path obvious.

## Risks / Trade-offs

**Breaking change for all Item subclasses** → Mitigated by the clear error message when `'id'` is still declared in FIELDS. The fix is a one-line removal. The UPGRADE.md / README should document this.

**`assignId()` is public** → Any code could call it, not just Repository. This is acceptable because Item methods are generally trusted (e.g. `clearOriginalValues()` is also public). The method name clearly signals it's for framework use. Documenting it as `@internal` provides additional signal.

**`getValues()` no longer includes id** → Code that relies on `$item->getValues()['id']` will break. Mitigated by including id in `jsonSerialize()` / `__serialize()` / `__toString()` output. For direct `getValues()` consumers, they should use `getId()` instead.

**ID_TYPE default assumes Integer** → If someone has a non-integer auto-increment PK (unlikely but possible), they need to override `ID_TYPE`. The default covers the vast majority of cases.
