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
