---------------------------------------
FormIt Webhook
---------------------------------------
Version: 1.0.0-pl
Author: Sterc <modx@sterc.nl>
---------------------------------------

FormIt Webhook

A FormIt hook for sending form submission data to external URLs via HTTP webhook.

Features

* Send form data via GET, POST, PUT, PATCH, or DELETE
* Three data formats: URL-encoded, multipart form-data, JSON
* Bearer token authentication
* Filter which form fields to include
* Add static key=value pairs to the request
* Configure via system settings or per-form scriptProperties

Properties

* url: The URL to send data to. Overrides the webhook_url system setting.
* token: Bearer token for authentication. Overrides the webhook_bearer_token system setting.
* method: HTTP method (GET, POST, PUT, PATCH, DELETE). Default: POST.
* webhookFormat: Data format - url, form, or json. Default: json.
* webhookFields: Comma-separated list of form fields to include. Empty includes all fields.
* webhookVars: Static key=value pairs to include, comma-separated. Example: source=website,form_id=contact

Requirements

* MODX >= 2.8
* FormIt >= 3.2
* PHP >= 7.3 with cURL extension
