<?php

namespace App\Models;

use App\Enums\ReceivableStatus;
use App\Exceptions\MalformedRecordException;

class Receivable
{
    private const REQUIRED = [
        'transaction_id',
        'status',
        'create_date',
        'subtotal',
        'discount',
        'total',
    ];

    public function __construct(
        public ?string $id,
        public string $transaction_id,
        public ReceivableStatus $status,
        public string $create_date,
        public string $subtotal,
        public string $discount,
        public string $total,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $reference = $data['id'] ?? '?';
        $missing = array_diff(self::REQUIRED, array_keys($data));

        if ($missing !== []) {
            throw new MalformedRecordException("Receivable [{$reference}] is missing: " . implode(', ', $missing) . '.');
        }

        $status = ReceivableStatus::tryFrom((string) $data['status']);

        if ($status === null) {
            throw new MalformedRecordException("Receivable [{$reference}] has an unknown status [{$data['status']}].");
        }

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            transaction_id: (string) $data['transaction_id'],
            status: $status,
            create_date: (string) $data['create_date'],
            subtotal: (string) $data['subtotal'],
            discount: (string) $data['discount'],
            total: (string) $data['total'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status->value,
            'create_date' => $this->create_date,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
        ];
    }
}
