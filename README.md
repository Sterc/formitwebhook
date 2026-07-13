# FormIt Webhook

A MODX FormIt hook that sends form submission data to any external URL via HTTP.

## Features

* Send form data via GET, POST, PUT, PATCH, or DELETE
* Three data formats: URL-encoded, multipart form-data, JSON
* Bearer token authentication
* Filter which form fields to include
* Add static key=value pairs to the request
* Configure defaults via system settings, override per form

## Installation

Install via the MODX package manager or GPM.

## System Settings

| Setting | Description | Default |
|---------|-------------|---------|
| `formit-webhook.webhook_url` | Default webhook URL | _(empty)_ |
| `formit-webhook.webhook_bearer_token` | Default Bearer token for authentication | _(empty)_ |
| `formit-webhook.webhook_method` | Default HTTP method | `POST` |
| `formit-webhook.webhook_static_data` | Default static key=value pairs, comma-separated | _(empty)_ |
| `formit-webhook.field_mapping` | Re-map form field names before sending, comma-separated `original=target` pairs (for Formalicious generic field names) | _(empty)_ |

## Properties

Properties are set as scriptProperties on the FormIt call. They override the corresponding system settings.

| Property | Description | Default |
|----------|-------------|---------|
| `url` | The URL to send data to | System setting |
| `token` | Bearer token for authentication | System setting |
| `method` | HTTP method: `GET`, `POST`, `PUT`, `PATCH`, `DELETE` | `POST` |
| `webhookFormat` | Data format: `url`, `form`, or `json` | `json` |
| `webhookFields` | Comma-separated list of form fields to include. Empty = all fields | _(all)_ |
| `webhookVars` | Static key=value pairs, comma-separated | System setting |
| `webhookFieldMapping` | Comma-separated `original=target` field-name pairs. Overrides the `field_mapping` system setting | System setting |

### Data Formats

| Format | Content-Type | Description |
|--------|-------------|-------------|
| `json` | `application/json` | Sends data as a JSON object in the request body |
| `url` | `application/x-www-form-urlencoded` | Sends data as URL-encoded form body |
| `form` | `multipart/form-data` | Sends data as multipart form data |

For `GET` requests, data is always appended as query string parameters regardless of the format setting.

## Usage Examples

### Basic: Send all fields as JSON POST

```php
[[!FormIt?
    &hooks=`FormitWebhook,redirect`
    &validate=`email:email:required,name:required`
    &redirectTo=`[[++page_thanks]]`
    &url=`https://api.example.com/leads`
    &token=`your-bearer-token`
]]
```

### Filtered fields with static data

```php
[[!FormIt?
    &hooks=`FormitWebhook,redirect`
    &validate=`email:email:required,name:required`
    &redirectTo=`[[++page_thanks]]`
    &url=`https://api.example.com/leads`
    &token=`your-bearer-token`
    &webhookFields=`name,email,phone`
    &webhookVars=`source=website,form_id=contact`
]]
```

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

### URL-encoded POST

```php
[[!FormIt?
    &hooks=`FormitWebhook`
    &validate=`email:email:required`
    &url=`https://api.example.com/submit`
    &webhookFormat=`url`
]]
```

### GET request

```php
[[!FormIt?
    &hooks=`FormitWebhook`
    &url=`https://api.example.com/notify`
    &method=`GET`
    &webhookFields=`email`
]]
```

### Error Handling

Add the webhook error placeholder to your form template to display errors to users:

```html
[[!+fi.error.webhook:notempty=`
    <p class="error">[[!+fi.error.webhook]]</p>
`]]
```

## Dependencies

* FormIt >= 3.2

## License

GPL v2
