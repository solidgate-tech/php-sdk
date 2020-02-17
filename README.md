# SolidGate API


This library provides basic API options of SolidGate payment gateway.

## Installation

### With Composer

```
$ composer require solidgate/php-sdk
```

```json
{
    "require": {
        "solidgate/php-sdk": "~1.0"
    }
}
```

## Usage

Card-gate example
```php
<?php

use SolidGate\API\Api;

$api = new Api('YourMerchantId', 'YourPrivateKey');

$response = $api->charge(['SomePaymentAttributes from API reference']);

```


Reconciliations example

```php
<?php

use SolidGate\API\Api;

$api = new Api('YourMerchantId', 'YourPrivateKey');

$dateFrom = new \DateTime("2019-01-01 00:00:00+00:00");
$dateTo = new \DateTime("2020-01-06 00:00:00+00:00");

$orderIterator = $api->getUpdatedOrders($dateFrom, $dateTo);
//$orderIterator = $api->getUpdatedChargebacks($dateFrom, $dateTo);
//$orderIterator = $api->getUpdatedAlerts($dateFrom, $dateTo);

foreach ($orderIterator as $order) {
    // process one order
}

```