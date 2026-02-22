# OpenSpec Workflow Notes

Project-level guidance that supplements the default OpenSpec skill instructions.

## Checkpoint discipline when applying changes

The tasks file in an OpenSpec change serves as a recovery log. If a session
crashes or is interrupted, only tasks marked `[x]` and committed to git can be
reliably recovered. To preserve this property, follow these rules when running
`opsx:apply`:

### 1. Checkpoint frequently

After completing a task — or a small, tightly-coupled group of tasks — immediately:

1. Mark the task(s) complete in the tasks file (`- [ ]` to `- [x]`)
2. Stage and commit all changed files with a message referencing the task(s)

**Tightly coupled** means changes that would be incoherent if split apart.
For example, a change to a class and the corresponding update to its unit test
are tightly coupled and may be completed together. Unrelated tasks are not.

**Never let more than 2-3 tasks accumulate** without marking them complete and
committing. If in doubt, checkpoint after every single task.

### 2. Commit after each checkpoint

Each checkpoint must include a git commit so progress is durable:

```
git add -A && git commit -m "task N: <short description>"
```

If multiple tightly-coupled tasks are checkpointed together:

```
git add -A && git commit -m "tasks N-M: <short description>"
```

### 3. Merge request guidance

When creating a merge request for an OpenSpec change, all per-task commits for
that change should be included by default. Before creating the MR, confirm with
the user whether they want to:

- Include all per-task commits as-is
- Squash them into a single commit
- Group them differently

### Why this matters

Without frequent checkpoints, a crash mid-session can lose significant progress.
The whole point of tracking tasks in a file is recoverability — but that only
works if the file is updated and committed as work progresses, not in bulk at
the end.
