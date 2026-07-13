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
