<?php

namespace SolidGate\API\DTO;

class FormResignDTO
{
    private $resignIntent;

    private $merchantId;

    private $signature;

    public function __construct(string $resignIntent, string $publicKey, string $signature)
    {
        $this->resignIntent = $resignIntent;
        $this->merchantId = $publicKey;
        $this->signature = $signature;
    }

    public function getResignIntent(): string
    {
        return $this->resignIntent;
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
            'resignIntent'  => $this->getResignIntent(),
            'merchant'      => $this->getMerchantId(),
            'signature'     => $this->getSignature(),
        ];
    }
}