<?php namespace SolidGate\API;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use Throwable;

class Api
{
    const BASE_SOLID_GATE_API_URI = 'https://pay.solidgate.com/api/v1/';
    const BASE_RECONCILIATION_API_URI = 'https://reports.solidgate.com/';

    const RECONCILIATION_AF_ORDER_PATH = 'api/v2/reconciliation/antifraud/order';
    const RECONCILIATION_ORDERS_PATH = 'api/v2/reconciliation/orders';
    const RECONCILIATION_CHARGEBACKS_PATH = 'api/v2/reconciliation/chargebacks';
    const RECONCILIATION_ALERTS_PATH = 'api/v2/reconciliation/chargeback-alerts';

    const FORM_PATTERN_URL = 'form?merchant=%s&form_data=%s&signature=%s';

    protected $solidGateApiClient;
    protected $reconciliationsApiClient;

    protected $merchantId;
    protected $privateKey;
    protected $exception;
    protected $formUrlPattern;

    public function __construct(
        string $merchantId,
        string $privateKey,
        string $baseSolidGateApiUri = self::BASE_SOLID_GATE_API_URI,
        string $baseReconciliationsApiUri = self::BASE_RECONCILIATION_API_URI
    ) {
        $this->merchantId = $merchantId;
        $this->privateKey = $privateKey;
        $this->formUrlPattern = $baseSolidGateApiUri . self::FORM_PATTERN_URL;

        $this->solidGateApiClient = new HttpClient(
            [
                'base_uri' => $baseSolidGateApiUri,
                'verify'   => true,
            ]
        );

        $this->reconciliationsApiClient = new HttpClient(
            [
                'base_uri' => $baseReconciliationsApiUri,
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

        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLen);

        $encrypt = openssl_encrypt($attributes, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $iv);
        $encrypt = $this->base64UrlEncode($iv . $encrypt);
        $signature = $this->generateSignature($encrypt);

        return sprintf($this->formUrlPattern, $this->getMerchantId(), $encrypt, $signature);
    }

    public function getUpdatedOrders(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ORDERS_PATH);
    }

    public function getUpdatedChargebacks(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_CHARGEBACKS_PATH);
    }

    public function getUpdatedAlerts(\DateTime $dateFrom, \DateTime $dateTo): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ALERTS_PATH);
    }

    public function getAntifraudOrderInformation(string $orderId): string
    {
        $request = $this->makeRequest(self::RECONCILIATION_AF_ORDER_PATH, [
            'order_id' => $orderId,
        ]);

        try {
            $response = $this->reconciliationsApiClient->send($request);

            return $response->getBody()->getContents();
        } catch (Throwable $e) {
            $this->exception = $e;
        }

        return '';
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
            $response = $this->solidGateApiClient->send($request);

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
        $urlEncoded = strtr(base64_encode($data), '+/', '-_');

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
                $response = $this->reconciliationsApiClient->send($request);
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
