<?php
$formitWebhook = $modx->getService(
    'formit-webhook',
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

$modx->lexicon->load('formit-webhook:default');

return $formitWebhook->submitForm($hook, $scriptProperties);