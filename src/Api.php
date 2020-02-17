<?php namespace SolidGate\API;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Throwable;

class Api
{
    const DEFAULT_BASE_URI = 'https://pay.solidgate.com/api/v1/';

    const RECONCILIATION_ORDERS_URI = 'https://reports.solidgate.com/api/v2/reconciliation/orders';
    const RECONCILIATION_CHARGEBACKS_URI = 'https://reports.solidgate.com/api/v2/reconciliation/chargebacks';
    const RECONCILIATION_ALERTS_URI = 'https://reports.solidgate.com/api/v2/reconciliation/chargeback-alerts';

    const FORM_PATTERN_URL = 'form?merchant=%s&form_data=%s&signature=%s';

    protected $client;
    protected $merchantId;
    protected $privateKey;
    protected $exception;
    protected $formUrlPattern;

    public function __construct(
        string $merchantId,
        string $privateKey,
        string $baseUri = self::DEFAULT_BASE_URI
    ) {
        $this->merchantId = $merchantId;
        $this->privateKey = $privateKey;
        $this->formUrlPattern = $baseUri . self::FORM_PATTERN_URL;

        $this->client = new Client(
            [
                'base_uri' => $baseUri,
                'verify'   => true,
            ]
        );
    }

    public function charge(array $attributes): string
    {
        return $this->sendRequest('charge', $attributes);
    }

    public function recurring(array $attributes): string
    {
        return $this->sendRequest('recurring', $attributes);
    }

    public function status(array $attributes): string
    {
        return $this->sendRequest('status', $attributes);
    }

    public function refund(array $attributes): string
    {
        return $this->sendRequest('refund', $attributes);
    }

    public function initPayment(array $attributes): string
    {
        return $this->sendRequest('init-payment', $attributes);
    }

    public function resign(array $attributes): string
    {
        return $this->sendRequest('resign', $attributes);
    }

    public function auth(array $attributes): string
    {
        return $this->sendRequest('auth', $attributes);
    }

    public function void(array $attributes): string
    {
        return $this->sendRequest('void', $attributes);
    }

    public function settle(array $attributes): string
    {
        return $this->sendRequest('settle', $attributes);
    }

    public function arnCode(array $attributes): string
    {
        return $this->sendRequest('arn-code', $attributes);
    }

    public function applePay(array $attributes): string
    {
        return $this->sendRequest('apple-pay', $attributes);
    }

    public function googlePay(array $attributes): string
    {
        return $this->sendRequest('google-pay', $attributes);
    }

    public function formUrl(array $attributes): string
    {
        $attributes = json_encode($attributes);
        $secretKey = substr($this->getPrivateKey(), 0, 32);
        $iv = substr($this->getPrivateKey(), 0, 16);

        $encrypt = openssl_encrypt($attributes, 'aes-256-cbc', $secretKey, 0, $iv);
        $encrypt = $this->base64UrlEncode($encrypt);
        $signature = $this->generateSignature($encrypt);

        return sprintf($this->formUrlPattern, $this->getMerchantId(), $encrypt, $signature);
    }

    public function getUpdatedOrders(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ORDERS_URI);
    }

    public function getUpdatedChargebacks(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_CHARGEBACKS_URI);
    }

    public function getUpdatedAlerts(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ALERTS_URI);
    }

    public function getMerchantId(): ?string
    {
        return $this->merchantId;
    }

    public function getPrivateKey(): ?string
    {
        return $this->privateKey;
    }

    public function generateSignature(string $data): string
    {
        return base64_encode(
            hash_hmac('sha512',
                $this->getMerchantId() . $data . $this->getMerchantId(),
                $this->getPrivateKey())
        );
    }

    public function sendRequest(string $method, array $attributes): string
    {
        $request = $this->makeRequest($method, $attributes);

        try {
            $response = $this->client->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
    }

    public function getException(): ?Throwable
    {
        return $this->exception;
    }

    protected function base64UrlEncode(string $data): string
    {
        $urlEncoded = strtr($data, '+/', '-_');

        return rtrim($urlEncoded, '=');
    }

    public function sendReconciliationsRequest(\DateTime $dateFrom, \DateTime $dateTo, string $url): \Generator {
        $nextPageIterator = null;
        do {
            $attributes = [
                'date_from' => $dateFrom->format('Y-m-d H:i:s'),
                'date_to'   => $dateTo->format('Y-m-d H:i:s'),
            ];

            if ($nextPageIterator) {
                $attributes['next_page_iterator'] = $nextPageIterator;
            }

            $request = $this->makeRequest($url, $attributes);
            try {
                $response = $this->client->send($request);
                $responseArray = json_decode($response->getBody()->getContents(), true);
                $nextPageIterator = ($responseArray['metadata'] ?? [])['next_page_iterator'] ?? null;

                foreach ($responseArray['orders'] as $order) {
                    yield $order;
                }
            } catch (Throwable $e) {
                $this->exception = $e;
                return;
            }
        } while ($nextPageIterator != null);
    }

    protected function makeRequest(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getMerchantId(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('POST', $path, $headers, $body);
    }
}
