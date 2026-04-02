<?php
/**
 * Default English Lexicon Entries for FormitWebhook
 *
 * @package formitwebhook
 * @subpackage lexicon
 */

$_lang['formitwebhook'] = 'FormIt Webhook';

// System setting labels and descriptions
$_lang['setting_formit-webhook.webhook_url']               = 'Webhook URL';
$_lang['setting_formit-webhook.webhook_url_desc']          = 'The default URL to send form data to.';
$_lang['setting_formit-webhook.webhook_bearer_token']      = 'Bearer Token';
$_lang['setting_formit-webhook.webhook_bearer_token_desc'] = 'The default Bearer token for webhook authentication.';
$_lang['setting_formit-webhook.webhook_method']            = 'HTTP Method';
$_lang['setting_formit-webhook.webhook_method_desc']       = 'The default HTTP method (GET, POST, PUT, PATCH, DELETE). Default: POST.';
$_lang['setting_formit-webhook.webhook_static_data']       = 'Static Data';
$_lang['setting_formit-webhook.webhook_static_data_desc']  = 'Default static key=value pairs to include, comma-separated. Example: source=website,form_id=contact';

// Error messages
$_lang['formitwebhook.error.no_url']         = 'Webhook failed: No URL was provided.';
$_lang['formitwebhook.error.invalid_method'] = 'Webhook failed: Invalid HTTP method "[[+method]]".';
$_lang['formitwebhook.error.invalid_format'] = 'Webhook failed: Invalid data format "[[+format]]".';
$_lang['formitwebhook.error.curl_error']     = 'Webhook failed: Could not connect to the remote server.';
$_lang['formitwebhook.error.http_error']     = 'Webhook failed: The remote server returned an error (HTTP [[+code]]).';
