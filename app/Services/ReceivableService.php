<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use App\Exceptions\ResourceNotFoundException;
use App\Models\Receivable;
use App\Models\Transaction;
use App\Services\Clients\JsonServerClient;
use Illuminate\Support\Carbon;

class ReceivableService
{
    private const RESOURCE = 'receivables';

    private const DATE_FORMAT = 'Y-m-d\TH:i:s.vP';

    public function __construct(private readonly JsonServerClient $client)
    {
    }

    /**
     * @return Receivable[]
     */
    public function list(): array
    {
        return array_map(Receivable::fromArray(...), $this->client->index(self::RESOURCE));
    }

    /**
     * Every receivable indexed by the transaction it belongs to, fetched in a single request.
     *
     * @return array<string, Receivable>
     */
    public function keyedByTransactionId(): array
    {
        $keyed = [];

        foreach ($this->list() as $receivable) {
            $keyed[$receivable->transaction_id] = $receivable;
        }

        return $keyed;
    }

    public function find(string $id): Receivable
    {
        $data = $this->client->show(self::RESOURCE, $id);

        if ($data === null) {
            throw new ResourceNotFoundException("Receivable [{$id}] not found.");
        }

        return Receivable::fromArray($data);
    }

    public function findByTransactionId(string $transactionId): ?Receivable
    {
        $matches = $this->client->index(self::RESOURCE, ['transaction_id' => $transactionId]);

        return $matches === [] ? null : Receivable::fromArray($matches[0]);
    }

    public function createForTransaction(Transaction $transaction, string $id): Receivable
    {
        $createdAt = Carbon::now();
        $subtotalCents = $this->toCents($transaction->value);
        $discount = (string) config("services.fees.{$transaction->method->value}");

        $receivable = new Receivable(
            id: $id,
            transaction_id: $transaction->id,
            status: $this->statusFor($transaction->method),
            create_date: $createdAt->format(self::DATE_FORMAT),
            subtotal: $this->money($subtotalCents),
            discount: $discount,
            total: $this->money($subtotalCents - $this->feeCents($subtotalCents, $discount)),
        );

        $this->client->store(self::RESOURCE, $receivable->toArray());

        return $receivable;
    }

    public function delete(string $id): void
    {
        if (!$this->deleteIfExists($id)) {
            throw new ResourceNotFoundException("Receivable [{$id}] not found.");
        }
    }

    public function deleteIfExists(string $id): bool
    {
        return $this->client->destroy(self::RESOURCE, $id);
    }

    private function statusFor(PaymentMethod $method): ReceivableStatus
    {
        return match ($method) {
            PaymentMethod::DebitCard => ReceivableStatus::Paid,
            PaymentMethod::CreditCard => ReceivableStatus::WaitingFunds,
        };
    }

    private function toCents(string $value): int
    {
        [$whole, $frac] = array_pad(explode('.', $value, 2), 2, '0');

        return (int) $whole * 100 + (int) substr($frac . '00', 0, 2);
    }

    private function feeCents(int $subtotalCents, string $discount): int
    {
        return intdiv($subtotalCents * (int) $discount + 50, 100);
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
