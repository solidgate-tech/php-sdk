# Solidgate API

[![PHP version](https://badge.fury.io/ph/solidgate%2Fphp-sdk.svg)](https://badge.fury.io/ph/solidgate%2Fphp-sdk)

PHP SDK provides API options for integrating Solidgate’s payment orchestrator into your PHP applications.

Check our
* <a href="https://docs.solidgate.com/" target="_blank">Payment guide</a> to understand business value better
* <a href="https://api-docs.solidgate.com/" target="_blank">API Reference</a> to find more examples of usage

## Structure

<table style="width: 100%; background: transparent;">
  <colgroup>
    <col style="width: 50%;">
    <col style="width: 50%;">
  </colgroup>
  <tr>
    <th>SDK for PHP contains</th>
    <th>Table of contents</th>
  </tr>
  <tr>
    <td>
      <code>src/solidgate/</code> – main library source code for development<br>
      <code>composer.json</code> – script for managing dependencies and library imports
    </td>
    <td>
      <a href="https://github.com/solidgate-tech/php-sdk?tab=readme-ov-file#requirements">Requirements</a><br>
      <a href="https://github.com/solidgate-tech/php-sdk?tab=readme-ov-file#installation">Installation</a><br>
      <a href="https://github.com/solidgate-tech/php-sdk?tab=readme-ov-file#usage">Usage</a><br>
      <a href="https://github.com/solidgate-tech/php-sdk?tab=readme-ov-file#errors">Errors</a>
    </td>
  </tr>
</table>

## Requirements

* **PHP**: 7.2 or later
* **Composer**: Dependency manager for PHP
* **Solidgate account**: Merchant ID and secret key (request via <a href="mailto:sales@solidgate.com">sales@solidgate.com</a>)

<br>

## Installation

To start using the PHP SDK:

1. Ensure you have your merchant ID and secret key.
2. Install the SDK in your project using Composer:
   ```bash
   composer require solidgate/php-sdk
   ```
3. Alternatively, add the library to your composer.json file
    ```json
    {
        "require": {
            "solidgate/php-sdk": "~1.0"
        }
    }
    ```
4. Import the installed libraries into your application.
5. Use test credentials to validate your integration.
6. After successful testing, request production credentials and deploy your service. <br> _Composer simplifies the installation and management of SDK dependencies, ensuring seamless integration._

<br>

## Usage

### Charge a payment

```php
<?php

use SolidGate\API\Api;

$api = new Api('YourMerchantId', 'YourPrivateKey');

$response = $api->charge(['SomePaymentAttributes from API reference']);

```

### Reconciliations

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

if ($api->getException() instanceof \Throwable) {
    // save exception to log and retry request (if necessary)
}
```

### Form resign

```php
<?php

use SolidGate\API\Api;

$api = new Api('YourMerchantId', 'YourPrivateKey');

$response = $api->formResign(['SomePaymentAttributes from API reference']);

$response->toArray(); // pass to your Frontend
```

<br>

## Errors

Handle <a href="https://docs.solidgate.com/payments/payments-insights/error-codes/" target="_blank">errors</a>, using a try/catch block.

```php
try {
        $response = $api->charge([...]);
        } catch (Throwable $e) {
        error_log($e->getMessage());
}
```
