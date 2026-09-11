<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Exceptions\MalformedRecordException;

class Transaction
{
    private const REQUIRED = [
        'value',
        'description',
        'method',
        'cardNumber',
        'cardHolderName',
        'cardExpirationDate',
        'cardCvv',
    ];

    public ?Receivable $receivable = null;

    public function __construct(
        public ?string $id,
        public string $value,
        public string $description,
        public PaymentMethod $method,
        public string $cardNumber,
        public string $cardHolderName,
        public string $cardExpirationDate,
        public string $cardCvv,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $reference = $data['id'] ?? '?';
        $missing = array_diff(self::REQUIRED, array_keys($data));

        if ($missing !== []) {
            throw new MalformedRecordException("Transaction [{$reference}] is missing: " . implode(', ', $missing) . '.');
        }

        $method = PaymentMethod::tryFrom((string) $data['method']);

        if ($method === null) {
            throw new MalformedRecordException("Transaction [{$reference}] has an unknown payment method [{$data['method']}].");
        }

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            value: (string) $data['value'],
            description: (string) $data['description'],
            method: $method,
            cardNumber: (string) $data['cardNumber'],
            cardHolderName: (string) $data['cardHolderName'],
            cardExpirationDate: (string) $data['cardExpirationDate'],
            cardCvv: (string) $data['cardCvv'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'value' => $this->value,
            'description' => $this->description,
            'method' => $this->method->value,
            'cardNumber' => $this->cardNumber,
            'cardHolderName' => $this->cardHolderName,
            'cardExpirationDate' => $this->cardExpirationDate,
            'cardCvv' => $this->cardCvv,
        ];
    }
}
