<?php

namespace SolidGate\API\DTO;

class FormUpdateDTO
{
    private $partialIntent;

    private $signature;

    public function __construct(string $partialIntent, string $signature)
    {
        $this->partialIntent = $partialIntent;
        $this->signature = $signature;
    }

    public function getPartialIntent(): string
    {
        return $this->partialIntent;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function toArray(): array
    {
        return [
            'partialIntent' => $this->getPartialIntent(),
            'signature'     => $this->getSignature(),
        ];
    }
}
