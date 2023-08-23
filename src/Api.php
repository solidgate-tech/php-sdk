<?php namespace SolidGate\API;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Request;
use SolidGate\API\DTO\FormInitDTO;
use SolidGate\API\DTO\FormUpdateDTO;
use SolidGate\API\DTO\MerchantData;
use Throwable;

class Api
{
    const BASE_SOLID_GATE_API_URI = 'https://pay.solidgate.com/api/v1/';
    const BASE_RECONCILIATION_API_URI = 'https://reports.solidgate.com/';

    const RECONCILIATION_AF_ORDER_PATH = 'api/v2/reconciliation/antifraud/order';
    const RECONCILIATION_ORDERS_PATH = 'api/v2/reconciliation/orders';
    const RECONCILIATION_CHARGEBACKS_PATH = 'api/v2/reconciliation/chargebacks';
    const RECONCILIATION_ALERTS_PATH = 'api/v2/reconciliation/chargeback-alerts';
    const RECONCILIATION_MAX_ATTEMPTS = 3;

    const RESIGN_FORM_PATTERN_URL = 'form/resign?merchant=%s&form_data=%s&signature=%s';

    protected $solidGateApiClient;
    protected $reconciliationsApiClient;

    protected $publicKey;
    protected $secretKey;
    protected $exception;
    private $resignFormUrlPattern;

    public function __construct(
        string $publicKey,
        string $secretKey,
        string $baseSolidGateApiUri = self::BASE_SOLID_GATE_API_URI,
        string $baseReconciliationsApiUri = self::BASE_RECONCILIATION_API_URI
    ) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->resignFormUrlPattern = $baseSolidGateApiUri . self::RESIGN_FORM_PATTERN_URL;

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

    public function resignFormUrl(array $attributes): string
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return sprintf($this->resignFormUrlPattern, $this->getPublicKey(), $encryptedFormData, $signature);
    }

    public function formMerchantData(array $attributes): FormInitDTO
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return new FormInitDTO($encryptedFormData, $this->getPublicKey(), $signature);
    }

    public function formUpdate(array $attributes): FormUpdateDTO
    {
        $encryptedFormData = $this->generateEncryptedFormData($attributes);
        $signature = $this->generateSignature($encryptedFormData);

        return new FormUpdateDTO($encryptedFormData, $signature);
    }

    public function getUpdatedOrders(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ORDERS_PATH, $maxAttempts);
    }

    public function getUpdatedChargebacks(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_CHARGEBACKS_PATH,
            $maxAttempts);
    }

    public function getUpdatedAlerts(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        int $maxAttempts = self::RECONCILIATION_MAX_ATTEMPTS
    ): \Generator {
        return $this->sendReconciliationsRequest($dateFrom, $dateTo, self::RECONCILIATION_ALERTS_PATH, $maxAttempts);
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

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function getSecretKey(): ?string
    {
        return $this->secretKey;
    }

    public function generateSignature(string $data): string
    {
        return base64_encode(
            hash_hmac('sha512',
                $this->getPublicKey() . $data . $this->getPublicKey(),
                $this->getSecretKey())
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
        return strtr(base64_encode($data), '+/', '-_');
    }

    public function sendReconciliationsRequest(
        \DateTime $dateFrom,
        \DateTime $dateTo,
        string $url,
        int $maxAttempts
    ): \Generator {
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
                $responseArray = $this->sendReconciliationsRequestInternal($request, $maxAttempts);
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

    private function sendReconciliationsRequestInternal(Request $request, int $maxAttempts): array
    {
        $attempt = 0;
        $lastException = null;
        while ($attempt < $maxAttempts) {
            $attempt += 1;
            try {
                $response = $this->reconciliationsApiClient->send($request);
                $responseArray = json_decode($response->getBody()->getContents(), true);
                if (is_array($responseArray) && isset($responseArray['orders']) && is_array($responseArray['orders'])) {
                    return $responseArray;
                }
                $lastException = new \RuntimeException("Incorrect response structure. Need retry request");
            } catch (Throwable $e) {
                $lastException = $e;
            }
        }

        throw new $lastException;
    }

    protected function generateEncryptedFormData(array $attributes): string
    {
        $attributes = json_encode($attributes);
        $secretKey = substr($this->getSecretKey(), 0, 32);

        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLen);

        $encrypt = openssl_encrypt($attributes, 'aes-256-cbc', $secretKey, OPENSSL_RAW_DATA, $iv);

        return $this->base64UrlEncode($iv . $encrypt);
    }

    protected function makeRequest(string $path, array $attributes): Request
    {
        $body = json_encode($attributes);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'Merchant'     => $this->getPublicKey(),
            'Signature'    => $this->generateSignature($body),
        ];

        return new Request('POST', $path, $headers, $body);
    }
}
