# Field Mapping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let FormitWebhook remap generic form field names (Formalicious's `field_1`, `field_2`) to meaningful names before the payload is sent.

**Architecture:** Add a pure-PHP `applyFieldMapping()` method to the existing `FormitWebhook` class that renames array keys per a `original=target` map, and call it inside `submitForm()` immediately after reading form values — before field filtering and static-data merging. Mapping is configured via a new `field_mapping` system setting, overridable per FormIt call via a `webhookFieldMapping` scriptProperty. Empty map = no-op (backward compatible).

**Tech Stack:** PHP (MODX extra / FormIt hook). Standalone PHP test scripts run with the `php` CLI — no composer, no phpunit (none exist in this repo).

## Global Constraints

- PHP `>=7.3` — do NOT use typed properties or arrow functions (7.4+). `??`, scalar type hints, and return type declarations are fine.
- MODX `>=2.8`, FormIt `>=3.2`.
- System setting namespace prefix is `formit-webhook.`; existing setting keys use a `webhook_` prefix, but this new key is `field_mapping` (no prefix) by explicit product decision.
- ScriptProperties are camelCase (`webhookFieldMapping`).
- Mapping string grammar is identical to `webhook_static_data`: comma-separated `key=value` pairs. Reuse the existing `parseVars()` helper — do NOT write a new parser.
- The `tests/` directory is NOT part of the built transport package (`_build/gpm.yaml` enumerates what ships), so test files may live at repo root safely.

---

### Task 1: `applyFieldMapping()` core method + unit tests

**Files:**
- Create: `tests/bootstrap.php`
- Create: `tests/test_field_mapping.php`
- Modify: `core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php` (add `applyFieldMapping()` method)

**Interfaces:**
- Consumes: nothing (pure array logic).
- Produces: `protected function applyFieldMapping(array $data, array $map): array` — returns `$data` with each key present in `$map` renamed from the map key (original) to the map value (target); the original key is removed; keys not in `$map` are untouched; an empty `$map` returns `$data` unchanged.

- [ ] **Step 1: Write the test bootstrap (shared MODX stub)**

Create `tests/bootstrap.php`:

```php
<?php
/**
 * Minimal test bootstrap. Defines a stand-in `modX` class so the FormitWebhook
 * model can be instantiated outside a MODX runtime, then loads the model.
 */
if (!class_exists('modX')) {
    class modX
    {
        const LOG_LEVEL_ERROR = 1;
        const LOG_LEVEL_INFO = 3;

        /** @var array key => value used by getOption() */
        public $options = [];

        public function log($level, $msg)
        {
            // no-op in tests
        }

        public function getOption($key, $options = null, $default = null)
        {
            return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
        }

        public function lexicon($key, $params = [])
        {
            return $key;
        }
    }
}

require_once __DIR__ . '/../core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php';

/**
 * Tiny assertion helper shared by all test scripts.
 */
function fw_check($label, $actual, $expected)
{
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL: {$label}\n"
            . '  expected: ' . json_encode($expected) . "\n"
            . '  actual:   ' . json_encode($actual) . "\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
```

- [ ] **Step 2: Write the failing unit test**

Create `tests/test_field_mapping.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';

$modx = new modX();
$fw = new FormitWebhook($modx);

$method = new ReflectionMethod(FormitWebhook::class, 'applyFieldMapping');
$method->setAccessible(true);

// Renames mapped keys, drops originals, keeps unmapped keys.
$out = $method->invoke($fw,
    ['field_1' => 'a@b.com', 'field_2' => 'Bob', 'message' => 'hi'],
    ['field_1' => 'email', 'field_2' => 'name']
);
fw_check('renames mapped and keeps unmapped', $out,
    ['message' => 'hi', 'email' => 'a@b.com', 'name' => 'Bob']);

// Empty map returns data unchanged.
$out = $method->invoke($fw, ['field_1' => 'x'], []);
fw_check('empty map unchanged', $out, ['field_1' => 'x']);

// A mapping whose original key is absent in data is skipped.
$out = $method->invoke($fw, ['field_2' => 'y'], ['field_1' => 'email']);
fw_check('absent original skipped', $out, ['field_2' => 'y']);

echo "All applyFieldMapping tests passed\n";
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php tests/test_field_mapping.php`
Expected: FAIL — a fatal error / `ReflectionException` because method `applyFieldMapping` does not exist yet.

- [ ] **Step 4: Implement `applyFieldMapping()`**

In `core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php`, add this method after `parseVars()` (after line 128, before the closing brace of the class):

```php
    /**
     * Rename data keys according to a mapping of original => target names.
     * Keys present in the map are renamed and the original key removed; keys
     * not in the map are left untouched. An empty map returns data unchanged.
     *
     * @param array $data
     * @param array $map original field name => target field name
     * @return array
     */
    protected function applyFieldMapping(array $data, array $map)
    {
        foreach ($map as $original => $target) {
            if (array_key_exists($original, $data)) {
                $data[$target] = $data[$original];
                unset($data[$original]);
            }
        }

        return $data;
    }
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php tests/test_field_mapping.php`
Expected: PASS — four `PASS:` lines then `All applyFieldMapping tests passed`.

- [ ] **Step 6: Lint the modified class**

Run: `php -l core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php`
Expected: `No syntax errors detected`.

- [ ] **Step 7: Commit**

```bash
git add tests/bootstrap.php tests/test_field_mapping.php core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php
git commit -m "feat: add applyFieldMapping() to remap form field names"
```

---

### Task 2: Wire mapping into `submitForm()` + integration test

**Files:**
- Modify: `core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php:64` (insert mapping step in `submitForm()`)
- Create: `tests/test_submit_mapping.php`

**Interfaces:**
- Consumes: `applyFieldMapping(array $data, array $map): array` (Task 1); the existing `getOption(string $propKey, array $scriptProperties, string $settingKey, string $default = ''): string` and `parseVars(string $varsString): array` helpers.
- Produces: `submitForm()` applies the mapping to `$hook->getValues()` output BEFORE the `webhookFields` filter and static-data merge, so downstream steps operate on target names.

- [ ] **Step 1: Write the failing integration test**

Create `tests/test_submit_mapping.php`:

```php
<?php
require __DIR__ . '/bootstrap.php';

/**
 * Subclass that captures the payload instead of sending an HTTP request.
 */
class CapturingWebhook extends FormitWebhook
{
    public $capturedData;

    protected function sendRequest($hook, $url, $method, $format, $token, array $data)
    {
        $this->capturedData = $data;
        return true;
    }
}

/**
 * Stand-in for the FormIt fiHooks object.
 */
class FakeHook
{
    private $values;
    public $errors = [];

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function getValues()
    {
        return $this->values;
    }

    public function addError($key, $value)
    {
        $this->errors[$key] = $value;
    }
}

$modx = new modX();
$modx->options = [
    'formit-webhook.webhook_url'   => 'https://example.com/hook',
    'formit-webhook.field_mapping' => 'field_1=email,field_2=name',
];

// Mapping applied via system setting; unmapped field_3 passes through.
$fw = new CapturingWebhook($modx);
$hook = new FakeHook(['field_1' => 'a@b.com', 'field_2' => 'Bob', 'field_3' => 'keepme']);
fw_check('submitForm returns true', $fw->submitForm($hook, []), true);
fw_check('mapping applied, unmapped kept', $fw->capturedData,
    ['field_3' => 'keepme', 'email' => 'a@b.com', 'name' => 'Bob']);

// webhookFields filters on the MAPPED (meaningful) names -> proves mapping runs first.
$fw2 = new CapturingWebhook($modx);
$hook2 = new FakeHook(['field_1' => 'a@b.com', 'field_2' => 'Bob', 'field_3' => 'drop']);
$fw2->submitForm($hook2, ['webhookFields' => 'email,name']);
fw_check('webhookFields filters on mapped names', $fw2->capturedData,
    ['email' => 'a@b.com', 'name' => 'Bob']);

// scriptProperty webhookFieldMapping overrides the system setting.
$fw3 = new CapturingWebhook($modx);
$hook3 = new FakeHook(['field_1' => 'ignored', 'field_9' => 'X']);
$fw3->submitForm($hook3, ['webhookFieldMapping' => 'field_9=code']);
fw_check('scriptProperty overrides system setting', $fw3->capturedData,
    ['field_1' => 'ignored', 'code' => 'X']);

echo "All submitForm mapping tests passed\n";
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php tests/test_submit_mapping.php`
Expected: FAIL — the first `fw_check` for `mapping applied` fails (payload still has `field_1`/`field_2`) because `submitForm()` does not apply the mapping yet. (The `scriptProperty overrides` case also fails.)

- [ ] **Step 3: Insert the mapping step in `submitForm()`**

In `core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php`, the current code reads (lines 63-70):

```php
        // Gather form data
        $data = $hook->getValues();

        // Filter fields if specified
        if (!empty($fields)) {
            $fieldList = array_map('trim', explode(',', $fields));
            $data = array_intersect_key($data, array_flip($fieldList));
        }
```

Replace it with:

```php
        // Gather form data
        $data = $hook->getValues();

        // Re-map generic field names (e.g. Formalicious field_1) to meaningful
        // names before filtering, so webhookFields and the endpoint see the
        // mapped names.
        $fieldMap = $this->parseVars($this->getOption('webhookFieldMapping', $scriptProperties, 'field_mapping'));
        $data = $this->applyFieldMapping($data, $fieldMap);

        // Filter fields if specified
        if (!empty($fields)) {
            $fieldList = array_map('trim', explode(',', $fields));
            $data = array_intersect_key($data, array_flip($fieldList));
        }
```

- [ ] **Step 4: Run the integration test to verify it passes**

Run: `php tests/test_submit_mapping.php`
Expected: PASS — five `PASS:` lines then `All submitForm mapping tests passed`.

- [ ] **Step 5: Re-run the unit test and lint (no regression)**

Run: `php tests/test_field_mapping.php && php -l core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php`
Expected: unit test still PASSes; `No syntax errors detected`.

- [ ] **Step 6: Commit**

```bash
git add core/components/formitwebhook/model/formitwebhook/formitwebhook.class.php tests/test_submit_mapping.php
git commit -m "feat: apply field mapping in submitForm before filtering"
```

---

### Task 3: Register system setting, lexicon entries, and docs

**Files:**
- Modify: `_build/gpm.yaml:19-22` (add `field_mapping` setting)
- Modify: `core/components/formitwebhook/lexicon/en/default.inc.php`
- Modify: `core/components/formitwebhook/lexicon/nl/default.inc.php`
- Modify: `README.md`

**Interfaces:**
- Consumes: setting key `field_mapping` and scriptProperty `webhookFieldMapping` as used in Task 2.
- Produces: installer-registered system setting + human-facing labels/docs. No code depends on this task.

- [ ] **Step 1: Add the system setting to the build manifest**

In `_build/gpm.yaml`, the `systemSettings:` list currently ends with the `webhook_static_data` block. Add the new entry immediately after it (after line 22), keeping the same 2-space indentation:

```yaml
  - key: field_mapping
    type: textfield
    area: default
    value:
```

- [ ] **Step 2: Add English lexicon entries**

In `core/components/formitwebhook/lexicon/en/default.inc.php`, after the `webhook_static_data_desc` line (line 19), add:

```php
$_lang['setting_formit-webhook.field_mapping']            = 'Field Mapping';
$_lang['setting_formit-webhook.field_mapping_desc']       = 'Re-map form field names before sending, comma-separated original=target pairs. Useful for Formalicious generic names. Example: field_1=email,field_2=name';
```

- [ ] **Step 3: Add Dutch lexicon entries**

First inspect the existing Dutch file to match its style:

Run: `cat core/components/formitwebhook/lexicon/nl/default.inc.php`

Then add the two matching keys alongside the other `setting_formit-webhook.*` entries in that file:

```php
$_lang['setting_formit-webhook.field_mapping']            = 'Veldtoewijzing';
$_lang['setting_formit-webhook.field_mapping_desc']       = 'Hernoem formuliervelden voor verzending, komma-gescheiden original=target paren. Handig voor generieke Formalicious-namen. Voorbeeld: field_1=email,field_2=name';
```

If the Dutch file has no `setting_formit-webhook.*` entries yet, place these after the `$_lang['formitwebhook']` line.

- [ ] **Step 4: Update the README**

In `README.md`, add a row to the System Settings table (after the `webhook_static_data` row):

```markdown
| `formit-webhook.field_mapping` | Re-map form field names before sending, comma-separated `original=target` pairs (for Formalicious generic field names) | _(empty)_ |
```

Add a row to the Properties table (after the `webhookVars` row):

```markdown
| `webhookFieldMapping` | Comma-separated `original=target` field-name pairs. Overrides the `field_mapping` system setting | System setting |
```

Add a new usage-examples subsection after the "Filtered fields with static data" example:

````markdown
### Formalicious: remap generic field names

Formalicious names fields generically (`field_1`, `field_2`, ...). Map them to
meaningful names before sending. Mapped keys are renamed and the originals
removed; unmapped fields pass through unchanged. If a mapped target name
collides with an existing field, the mapped value wins.

Set the `formit-webhook.field_mapping` system setting to
`field_1=email,field_2=name`, or per form:

```php
[[!FormIt?
    &hooks=`FormitWebhook,redirect`
    &redirectTo=`[[++page_thanks]]`
    &url=`https://api.example.com/leads`
    &webhookFieldMapping=`field_1=email,field_2=name`
]]
```
````

- [ ] **Step 5: Lint lexicon files**

Run: `php -l core/components/formitwebhook/lexicon/en/default.inc.php && php -l core/components/formitwebhook/lexicon/nl/default.inc.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 6: Commit**

```bash
git add _build/gpm.yaml core/components/formitwebhook/lexicon/en/default.inc.php core/components/formitwebhook/lexicon/nl/default.inc.php README.md
git commit -m "feat: register field_mapping setting, lexicon and docs"
```

---

## Notes for the implementer

- Run all tests at the end with: `php tests/test_field_mapping.php && php tests/test_submit_mapping.php`
- The `applyFieldMapping()` method must stay free of any `$this->modx` usage — it is pure array logic and the unit test instantiates it without a real MODX.
- Do not reformat unrelated lines in the class file; keep the diff minimal.
