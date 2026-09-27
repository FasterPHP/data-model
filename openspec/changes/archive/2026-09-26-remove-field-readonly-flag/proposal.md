## Why

`Field\Base` carries a readonly flag that nothing enforces and nothing reads. `Item` sets it when it
builds each field (`$inReadonly || $inExternal || $inAggregate`) and passes it to the Field
constructor, and `Field\Base` exposes `setReadonly()` and `isReadonly()`. But `Field\Base::setValue()`
ignores the flag, and `Item::setValue()` enforces protection by checking the `FIELDS_READONLY`,
`FIELDS_EXTERNAL` and `FIELDS_AGGREGATE` constants directly, never the Field. No code in this library,
or in the known consumers, calls `isReadonly()`.

Enforcement moved from Field to Item deliberately, in `allow-readonly-set-on-temp-items`, because
readonly means write-once: settable on a new Item, immutable once persisted. A Field cannot know which
of those states its Item is in, so the check belongs to Item. The flag was left behind.

The result is a Field that reports itself readonly while accepting any value. That has already misled
a consumer: four of its tests were written trusting
the flag and have failed since the enforcement moved. Two comments also still describe an initial value
as bypassing "the readonly check", a check that no longer exists at Field level.

The class is public, but this branch has not been merged and already carries breaking changes, so
removing the flag now costs less than removing it after release.

## What Changes

- **BREAKING**: `Field\Base::setReadonly()` and `Field\Base::isReadonly()` are removed, along with the
  `$isReadonly` property they wrapped.
- **BREAKING**: the `$isReadonly` constructor parameter is removed, so the constructor becomes
  `__construct(string $name, mixed $initialValue = null)`. The test that detects an explicitly supplied
  initial value, currently `func_num_args() >= 3`, becomes `func_num_args() >= 2`.
- `Item` stops computing and passing the flag when it builds fields.
- The two stale "bypasses readonly check" comments, one in `Field\Base` and one in `Item`, are removed
  or corrected.
- `Item::FIELDS_READONLY` gains a docblock stating what it actually means: the field may be set on a new
  Item and is immutable once the Item has been persisted.

Behaviour is otherwise unchanged. `Item::setValue()` enforces exactly what it enforces now.

### Decision: remove the constructor parameter, accepting a silent failure mode

Removing a *middle* parameter does not fail loudly. PHP silently ignores surplus arguments to a userland
function, so a caller written for the old signature, such as `new Field\Integer('x', true, 42)`, would
not error. It would receive `true` as its initial value and discard `42`.

Keeping the parameter as an ignored, deprecated argument would avoid that, but would leave a parameter
that does nothing on every Field constructor indefinitely. The only positional callers are `Item`, which
this change updates, and one consumer test, which is being rewritten
in that consumer regardless. No consumer's source constructs a Field directly. So the parameter is removed outright.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. No spec describes the Field-level flag or its methods. The protection rules that do exist are
Item-level and are unchanged. This change therefore sets `skip_specs: true`.

`design.md` is not included. The change has no architectural, dependency, security, performance or
migration dimension beyond the one decision above, which is recorded here.

## Impact

**Affected code**

- `src/Field/Base.php`: the property, both methods, the constructor parameter, the `func_num_args()`
  check, and the stale comments.
- `src/Item.php`: field construction no longer computes or passes the flag, the stale comment goes, and
  `FIELDS_READONLY` gains its docblock.
- `tests/FieldTest.php`: `testSetReadonly()` and `testSetReadonlyToggleOff()` are deleted, since they
  test only the removed methods.

**Affected consumers**

- One consumer's test suite calls `isReadonly()` and
  constructs a Field positionally with the flag. After this change those calls fail with "Call to
  undefined method", and the positional construction passes the wrong initial value silently. Those
  tests already fail today and are due to be rewritten against Item-level protection as part of bringing
  that consumer up to date, so no separate change is needed there. Nothing in its source uses the flag.
- Other known consumers: not affected.

**No dependency changes.**
