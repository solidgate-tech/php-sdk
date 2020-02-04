# SolidGate API


This library provides basic API options of SolidGate payment gateway.

## Installation

### With Composer

```
$ composer require solidgate/api
```

```json
{
    "require": {
        "solidgate/api": "~1.0"
    }
}
```

## Usage

```php
<?php

use SolidGate\API\Api;

$api = new Api('YourMerchantId', 'YourPrivateKey');

$response = $api->charge(['SomePaymentAttributes from API reference']);

```