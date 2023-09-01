<?php

namespace Onramplab\LaravelLogEnhancement\Handlers;

use Exception;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\Curl\Util;
use Monolog\Handler\MissingExtensionException;
use Monolog\Logger;
use Monolog\LogRecord;

class DatadogHandler extends AbstractProcessingHandler
{
    protected const BASE_HOST = 'datadoghq.com';

    /**
     * Datadog Api Key access
     */
    private string $key;

    /**
     * Datadog Api Host
     */
    private string $apiHost;

    /**
     * Datadog optionals attributes
     */
    private array $attributes;

    /**
     * @param string     $key        Datadog Api Key access
     * @param string     $region     Datadog Region
     * @param array      $attributes Some options fore Datadog Logs
     * @param string|int $level      The minimum logging level at which this handler will be triggered
     * @param bool       $bubble     Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(
        string $key,
        string $region = null,
        array $attributes = [],
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        if (!extension_loaded('curl')) {
            throw new MissingExtensionException('The curl extension is needed to use the DatadogHandler');
        }

        parent::__construct($level, $bubble);

        $this->key = $this->getApiKey($key);
        $this->apiHost = $this->getApiHost($region);
        $this->attributes = $attributes;
    }

    /**
     * Handles a log record
     */
    protected function write(LogRecord $record): void
    {
        $this->send($record['formatted']);
    }

    /**
     * Send request to @link https://docs.datadoghq.com/api/latest/logs/?code-lang=curl#send-logs
     * @param string $record
     */
    protected function send($record): void
    {
        $parameters = [
            'ddsource' => $this->getSource(),
            'hostname' => $this->getHostname(),
            'service' => $this->getService($record),
            'ddtags' =>  $this->getTags()
        ];
        $queryString = http_build_query($parameters);
        $url = sprintf('https://http-intake.logs.%s/api/v2/logs?%s', $this->apiHost, $queryString);

        $headers = [
            'DD-API-KEY: ' . $this->key,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $record);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        Util::execute($ch);
    }

    /**
     * Get Datadog Api Key.
     * @param string $key
     *
     * @return string
     */
    protected function getApiKey($key)
    {
        if ($key) {
            return $key;
        } else {
            throw new Exception('The Datadog Api Key is required');
        }
    }

    /**
     * Get Datadog Region host.
     * @param ?string $region
     *
     * @return string
     */
    protected function getApiHost($region)
    {
        return $region ? sprintf("%s.%s", $region, self::BASE_HOST) : self::BASE_HOST;
    }
    /**
     * Get Datadog Source from $attributes params.
     *
     * @return string
     */
    protected function getSource()
    {
        return !empty($this->attributes['source']) ? $this->attributes['source'] : 'php';
    }

    /**
     * Get Datadog Service from $attributes params.
     *
     * @return string
     */
    protected function getService($record)
    {
        $channel = json_decode($record, true);

        return !empty($this->attributes['service']) ? $this->attributes['service'] : $channel['channel'];
    }

    /**
     * Get Datadog Hostname from $attributes params.
     *
     * @return string
     */
    protected function getHostname()
    {
        return !empty($this->attributes['hostname']) ? $this->attributes['hostname'] : $_SERVER['SERVER_NAME'];
    }

    /**
     * Get Datadog Tags from $attributes params.
     *
     * @return string
     */
    protected function getTags()
    {
        return !empty($this->attributes['tags']) ? $this->attributes['tags'] : '';
    }

    /**
     * Returns the default formatter to use with this handler
     *
     * @return JsonFormatter
     */
    protected function getDefaultFormatter(): JsonFormatter
    {
        return new JsonFormatter();
    }
}
