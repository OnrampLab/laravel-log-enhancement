# laravel-log-enhancement

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Tests](https://github.com/OnrampLab/laravel-log-enhancement/actions/workflows/tests.yml/badge.svg)](https://github.com/OnrampLab/laravel-log-enhancement/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/onramplab/laravel-log-enhancement.svg?style=flat-square)](https://packagist.org/packages/onramplab/laravel-log-enhancement)

An enhanced logging package for Laravel that provides end-to-end request tracing, automatic context enrichment, and integration with modern observability tools.

## Features

- **Trace ID Propagation**: Seamlessly correlates logs across Web requests and Queue jobs.
- **Auto-Context Enrichment**: Automatically adds `class_path` and `tracking_id` to every log entry.
- **AWS ALB / ELK Support**: Automatically detects AWS trace identifiers (`X-Amzn-Trace-Id`).
- **Standard Header Support**: Uses `X-Request-Id` for external trace correlation.
- **Loggly Integration**: Extends monolog's LogglyHandler with native tag support.
- **Datadog Support**: Built-in support for Datadog Logs and APM log-trace correlation.

## Installation

```bash
composer require onramplab/laravel-log-enhancement
```

### Datadog APM Integration (Optional)
To connect PHP logs and traces using Datadog:
```bash
curl -LO https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php
sudo php datadog-setup.php --php-bin=all
composer require datadog/dd-trace:^0.90
```

## Trace ID Support

The package automatically handles the lifecycle of a `trace-id` (exported as `X-Request-Id` in responses):

1.  **Web**: Automatically captures IDs from `X-Amzn-Trace-Id`, `X-Request-Id`, or generates a new UUID.
2.  **Queue**: Automatically injects the current `trace-id` into job payloads and restores it when the worker starts processing.
3.  **CLI/Cron**: Automatically generates a fallback `trace-id` on boot for consistency.

No manual configuration is required as the middleware is automatically registered.

## Usage

### Enhanced Logs

Every log entry will automatically include enrichment data in the context:

```json
{
  "message": "User registered",
  "context": {
    "class_path": "App\\Http\\Controllers\\RegisterController",
    "tracking_id": "652c3456-1a17-42b8-9fa7-9bee65e655eb"
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "local",
  "timestamp": "2024-03-13T22:47:56.598608-0800"
}
```

### LogglyHandler

Add the following to your `config/logging.php`:

```php
use Monolog\Formatter\LogglyFormatter;
use Onramplab\LaravelLogEnhancement\Handlers\LogglyHandler;

'loggly' => [
    'driver' => 'monolog',
    'level' => 'info',
    'handler' => LogglyHandler::class,
    'handler_with' => [
        'token' => env('LOGGLY_TOKEN'),
        'tags' => env('LOGGLY_TAGS'),
    ],
    'formatter' => LogglyFormatter::class,
],
```

### DatadogHandler

Add the following to your `config/logging.php`:

```php
use Monolog\Formatter\JsonFormatter;
use Onramplab\LaravelLogEnhancement\Handlers\DatadogHandler;

'datadog' => [
  'driver' => 'monolog',
  'level' => 'info',
  'handler' => DatadogHandler::class,
  'handler_with' => [
      'key' => env('DD_LOG_API_KEY'),
      'region' => env('DD_LOG_REGION', 'us5'),
      'attributes' => [
          'hostname' => gethostname(),
          'source' => env('DD_LOG_SOURCE', 'laravel'),
          'service' => env('DD_LOG_SERVICE'),
          'tags' => env('DD_LOG_TAG'),
      ],
  ],
  'formatter' => JsonFormatter::class,
],
```

## Testing

Run the tests with:

```bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email kos.huang@onramplab.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](/LICENSE.md) for more information.
