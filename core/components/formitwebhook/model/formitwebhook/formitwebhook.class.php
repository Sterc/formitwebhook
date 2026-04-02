<?php

class FormitWebhook
{
    /** @var modX */
    public $modx;

    /** @var string */
    protected $namespace = 'formit-webhook';

    /** @var array */
    protected $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var array */
    protected $allowedFormats = ['url', 'form', 'json'];

    /**
     * @param modX $modx
     * @param array $config
     */
    public function __construct(modX &$modx, array $config = [])
    {
        $this->modx =& $modx;
    }

    /**
     * FormIt hook entry point. Sends form data to an external URL.
     *
     * @param fiHooks $hook
     * @param array $scriptProperties
     * @return bool
     */
    public function submitForm($hook, array $scriptProperties = [])
    {
        $url = $this->getOption('url', $scriptProperties, 'webhook_url');
        $token = $this->getOption('token', $scriptProperties, 'webhook_bearer_token');
        $method = strtoupper($this->getOption('method', $scriptProperties, 'webhook_method', 'POST'));
        $format = strtolower($scriptProperties['webhookFormat'] ?? 'json');
        $fields = $scriptProperties['webhookFields'] ?? '';
        $vars = $this->getOption('webhookVars', $scriptProperties, 'webhook_static_data');

        // Validate URL
        if (empty($url)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormitWebhook] No URL provided.');
            $hook->addError('webhook', $this->modx->lexicon('formitwebhook.error.no_url'));
            return false;
        }

        // Validate method
        if (!in_array($method, $this->allowedMethods)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormitWebhook] Invalid HTTP method: ' . $method);
            $hook->addError('webhook', $this->modx->lexicon('formitwebhook.error.invalid_method', ['method' => $method]));
            return false;
        }

        // Validate format
        if (!in_array($format, $this->allowedFormats)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormitWebhook] Invalid data format: ' . $format);
            $hook->addError('webhook', $this->modx->lexicon('formitwebhook.error.invalid_format', ['format' => $format]));
            return false;
        }

        // Gather form data
        $data = $hook->getValues();

        // Filter fields if specified
        if (!empty($fields)) {
            $fieldList = array_map('trim', explode(',', $fields));
            $data = array_intersect_key($data, array_flip($fieldList));
        }

        // Merge static vars (system setting first, then scriptProperty overrides)
        $staticData = $this->parseVars($this->modx->getOption($this->namespace . '.webhook_static_data', null, ''));
        $scriptVars = $this->parseVars($vars);
        $mergedVars = array_merge($staticData, $scriptVars);

        // Only apply system setting vars if vars scriptProperty was also provided (avoid double-applying)
        if (!empty($scriptProperties['webhookVars'])) {
            $data = array_merge($data, $mergedVars);
        } else {
            $data = array_merge($data, $staticData);
        }

        // Send the request
        return $this->sendRequest($hook, $url, $method, $format, $token, $data);
    }

    /**
     * Resolve a parameter from scriptProperties or system settings.
     *
     * @param string $propKey
     * @param array $scriptProperties
     * @param string $settingKey
     * @param string $default
     * @return string
     */
    protected function getOption($propKey, array $scriptProperties, $settingKey, $default = '')
    {
        if (!empty($scriptProperties[$propKey])) {
            return $scriptProperties[$propKey];
        }

        return $this->modx->getOption($this->namespace . '.' . $settingKey, null, $default);
    }

    /**
     * Parse a comma-separated key=value string into an associative array.
     *
     * @param string $varsString
     * @return array
     */
    protected function parseVars($varsString)
    {
        $result = [];
        if (empty($varsString)) {
            return $result;
        }

        $pairs = array_map('trim', explode(',', $varsString));
        foreach ($pairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $result[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $result;
    }

    /**
     * Build and send the cURL request.
     *
     * @param fiHooks $hook
     * @param string $url
     * @param string $method
     * @param string $format
     * @param string $token
     * @param array $data
     * @return bool
     */
    protected function sendRequest($hook, $url, $method, $format, $token, array $data)
    {
        $headers = [];
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();

        // For GET requests, append data as query string
        if ($method === 'GET') {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . http_build_query($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ]);

        if ($method !== 'GET') {
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            } else {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            }

            switch ($format) {
                case 'json':
                    $headers[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    break;
                case 'form':
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                    break;
                case 'url':
                default:
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                    break;
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if (!empty($curlError)) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormitWebhook] cURL error: ' . $curlError);
            $hook->addError('webhook', $this->modx->lexicon('formitwebhook.error.curl_error'));
            return false;
        }

        // Handle HTTP errors
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[FormitWebhook] HTTP ' . $httpCode . ' from ' . $url . ': ' . $response);
            $hook->addError('webhook', $this->modx->lexicon('formitwebhook.error.http_error', ['code' => $httpCode]));
            return false;
        }

        $this->modx->log(modX::LOG_LEVEL_INFO, '[FormitWebhook] Successfully sent data to ' . $url . ' (HTTP ' . $httpCode . ')');
        return true;
    }
}
