# Monolog Tracy Handler

* Integrates [Tracy](https://tracy.nette.org) into [Monolog](https://github.com/Seldaek/monolog)
* Supports uploading Tracy bluescreens to AWS S3


## Installation

```bash
composer require mangoweb/monolog-tracy-handler
```


## Usage with Symfony

Install [symfony/monolog-bundle](https://github.com/symfony/monolog-bundle) and add to `config/services.yaml`

```yaml
services:
    Mangoweb\MonologTracyHandler\TracyProcessor:
        tags:
            - { name: monolog.processor }

    Mangoweb\MonologTracyHandler\TracyHandler:
        arguments:
            $localBlueScreenDirectory: '%kernel.logs_dir%'
        tags:
            - { name: monolog.logger }
```

You can optionally configure remote storage for Tracy bluescreens.

```yaml
services:
    Mangoweb\MonologTracyHandler\RemoteStorageDriver:
        class: Mangoweb\MonologTracyHandler\RemoteStorageDrivers\AwsS3RemoteStorageDriver
        arguments:
            $region: '...'
            $bucket: '...'
            $prefix: 'tracy/'
            $accessKeyId: '...'
            $secretKey: '...'

    Mangoweb\MonologTracyHandler\RemoteStorageRequestSender:
        class: Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders\ExecCurlRequestSender
```

## Usage with Nette

Install [contributte/monolog](https://github.com/contributte/monolog) and add to `app/config/config.neon`

```yaml
extensions:
    monolog: Contributte\Monolog\DI\MonologExtension

monolog:
    channel:
        default:
            processors:
                - Mangoweb\MonologTracyHandler\TracyProcessor

            handlers:
                - Mangoweb\MonologTracyHandler\TracyHandler('%appDir%/../log')
```

You can optionally configure remote storage for Tracy bluescreens.

```yaml
services:
    monologTracyStorageDriver:
        type: Mangoweb\MonologTracyHandler\RemoteStorageDriver
        factory: Mangoweb\MonologTracyHandler\RemoteStorageDrivers\AwsS3RemoteStorageDriver
        arguments:
            region: '...'
            bucket: '...'
            prefix: 'tracy/'
            accessKeyId: '...'
            secretKey: '...'

    monologTracyRequestSender:
        type: Mangoweb\MonologTracyHandler\RemoteStorageRequestSender
        factory: Mangoweb\MonologTracyHandler\RemoteStorageRequestSenders\ExecCurlRequestSender
```
