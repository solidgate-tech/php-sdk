<?php

namespace SolidGate\API\DTO;

class FormInitDTO
{
    private $paymentIntent;

    private $merchantId;

    private $signature;

    public function __construct(string $paymentIntent, string $publicKey, string $signature)
    {
        $this->paymentIntent = $paymentIntent;
        $this->merchantId = $publicKey;
        $this->signature = $signature;
    }

    public function getPaymentIntent(): string
    {
        return $this->paymentIntent;
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function toArray(): array
    {
        return [
            'paymentIntent' => $this->getPaymentIntent(),
            'merchant'      => $this->getMerchantId(),
            'signature'     => $this->getSignature(),
        ];
    }
}
