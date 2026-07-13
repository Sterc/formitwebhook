<?php
/**
 * Default Dutch Lexicon Entries for FormitWebhook
 *
 * @package formitwebhook
 * @subpackage lexicon
 */

$_lang['formitwebhook'] = 'FormIt Webhook';

// System setting labels and descriptions
$_lang['setting_formit-webhook.webhook_url']               = 'Webhook URL';
$_lang['setting_formit-webhook.webhook_url_desc']          = 'De standaard URL waarnaar formulierdata wordt verstuurd.';
$_lang['setting_formit-webhook.webhook_bearer_token']      = 'Bearer Token';
$_lang['setting_formit-webhook.webhook_bearer_token_desc'] = 'Het standaard Bearer token voor webhook authenticatie.';
$_lang['setting_formit-webhook.webhook_method']            = 'HTTP Methode';
$_lang['setting_formit-webhook.webhook_method_desc']       = 'De standaard HTTP methode (GET, POST, PUT, PATCH, DELETE). Standaard: POST.';
$_lang['setting_formit-webhook.webhook_static_data']       = 'Statische Data';
$_lang['setting_formit-webhook.webhook_static_data_desc']  = 'Standaard statische key=value paren om mee te sturen, komma-gescheiden. Voorbeeld: source=website,form_id=contact';
$_lang['setting_formit-webhook.field_mapping']             = 'Veldtoewijzing';
$_lang['setting_formit-webhook.field_mapping_desc']        = 'Hernoem formuliervelden voor verzending, komma-gescheiden original=target paren. Handig voor generieke Formalicious-namen. Voorbeeld: field_1=email,field_2=name';

// Error messages
$_lang['formitwebhook.error.no_url']         = 'Webhook mislukt: Er is geen URL opgegeven.';
$_lang['formitwebhook.error.invalid_method'] = 'Webhook mislukt: Ongeldige HTTP methode "[[+method]]".';
$_lang['formitwebhook.error.invalid_format'] = 'Webhook mislukt: Ongeldig dataformaat "[[+format]]".';
$_lang['formitwebhook.error.curl_error']     = 'Webhook mislukt: Kan geen verbinding maken met de externe server.';
$_lang['formitwebhook.error.http_error']     = 'Webhook mislukt: De externe server gaf een fout terug (HTTP [[+code]]).';
