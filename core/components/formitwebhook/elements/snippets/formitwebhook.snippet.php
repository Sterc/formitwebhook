<?php
$formitWebhook = $modx->getService(
    'formitwebhook',
    'FormitWebhook',
    $modx->getOption(
        'formitwebhook.core_path',
        null,
        $modx->getOption('core_path') . 'components/formitwebhook/'
    ) . 'model/formitwebhook/'
);

if (!($formitWebhook instanceof FormitWebhook)) {
    return;
}

$modx->lexicon->load('formitwebhook:default');

return $formitWebhook->submitForm($hook, $scriptProperties);