# FormitWebhook Field Mapping — Design

**Date:** 2026-07-13
**Status:** Approved

## Problem

FormitWebhook sends FormIt form data to an external endpoint. It assumes form
field names are meaningful (`email`, `name`), which the user controls in stock
FormIt. The **Formalicious** form builder does not let the user name fields;
it emits generic names like `field_1`, `field_2`. As a result the payload sent
to the endpoint contains meaningless keys.

We need a way to remap those generic field names to meaningful names before the
payload is sent, without changing how the extra behaves for existing FormIt
users.

## Solution overview

Add a field mapping step that renames form field keys according to a
user-defined `original=target` mapping, before the payload is filtered and
sent. Mapping is configured via a new system setting, overridable per FormIt
call via a new scriptProperty. When no mapping is configured, behaviour is
unchanged (fully backward-compatible).

## Configuration surface

### System setting

| Key | Type | Area | Default |
|-----|------|------|---------|
| `formit-webhook.field_mapping` | `textfield` | `default` | _(empty)_ |

Value is a comma-separated list of `original=target` pairs, identical in format
to the existing `webhook_static_data` setting:

```
field_1=email,field_2=name,field_3=message
```

### ScriptProperty

| Property | Description | Default |
|----------|-------------|---------|
| `webhookFieldMapping` | Comma-separated `original=target` pairs. Overrides the system setting per FormIt call. | System setting |

Resolved through the existing `getOption('webhookFieldMapping', $scriptProperties, 'field_mapping')`
helper — the same fallback pattern used by `url`, `token`, and `method`.

## Parsing

Reuse the existing `parseVars()` helper. The mapping string uses the same
`key=value,key=value` grammar as `webhookVars`, so:

```
"field_1=email,field_2=name" => ['field_1' => 'email', 'field_2' => 'name']
```

No new parser is required.

## Remapping logic

New protected method:

```php
protected function applyFieldMapping(array $data, array $map): array
```

For each `original => target` pair in `$map`:

- If `$data` has the key `original`: set `$data[target] = $data[original]` and
  `unset($data[original])`.
- If `$data` does not have `original`: skip (nothing to rename).

Fields **not** listed in the map pass through untouched. An empty map returns
`$data` unchanged.

**Collision note:** if a mapped `target` equals the name of an existing field
in `$data` (e.g. mapping `field_1=email` when a real `email` field also
exists), the mapped value overwrites the existing one. This is unlikely with
Formalicious's generic names but will be documented in the README.

## Order of operations in `submitForm()`

Mapping runs **first**, so that `webhookFields` and static-data merging operate
on the meaningful names:

```
1. $data = $hook->getValues();              // field_1, field_2, message, ...
2. $data = applyFieldMapping($data, $map);  // -> email, name, message, ...   (NEW)
3. filter by webhookFields                   // uses the meaningful names
4. merge static data (webhookVars / webhook_static_data)
5. sendRequest(...)
```

Rationale: a Formalicious user configures the mapping once, then references the
meaningful names (`email`, `name`) everywhere else — in `webhookFields`, in the
receiving endpoint, and in their mental model. No mapping configured means step
2 is a no-op and existing behaviour is preserved.

## Files touched

| File | Change |
|------|--------|
| `core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php` | Read mapping option in `submitForm()`, apply it before filtering; add `applyFieldMapping()` method |
| `_build/gpm.yaml` | Add `field_mapping` system setting entry |
| `core/components/formitwebhook/lexicon/en/default.inc.php` | Add `setting_formit-webhook.field_mapping` label + `_desc` |
| `core/components/formitwebhook/lexicon/nl/default.inc.php` | Add Dutch label + `_desc` |
| `README.md` | Add `field_mapping` to settings table, `webhookFieldMapping` to properties table, add a Formalicious usage example |

No new files, no schema changes, no dependency changes.

## Out of scope (YAGNI)

- Bidirectional / regex / wildcard mappings — only exact `original=target`.
- Auto-dropping unmapped fields — use `webhookFields` for that.
- Value transformation — mapping renames keys only, values are untouched.
