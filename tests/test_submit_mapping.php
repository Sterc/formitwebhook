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
    'formit-webhook.webhook_field_mapping' => 'field_1=email,field_2=name',
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
