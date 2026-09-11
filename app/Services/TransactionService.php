<?php

namespace App\Services;

use App\Exceptions\ResourceNotFoundException;
use App\Exceptions\TransactionCreationFailedException;
use App\Models\Transaction;
use App\Services\Clients\JsonServerClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionService
{
    private const RESOURCE = 'transactions';

    public function __construct(
        private readonly JsonServerClient $client,
        private readonly NumeratorService $numerator,
        private readonly ReceivableService $receivables,
    ) {
    }

    public function list(): array
    {
        $transactions = array_map(Transaction::fromArray(...), $this->client->index(self::RESOURCE));
        $receivables = $this->receivables->keyedByTransactionId();

        foreach ($transactions as $transaction) {
            $transaction->receivable = $receivables[$transaction->id] ?? null;
        }

        return $transactions;
    }

    public function find(string $id): Transaction
    {
        $data = $this->client->show(self::RESOURCE, $id);

        if ($data === null) {
            throw new ResourceNotFoundException("Transaction [{$id}] not found.");
        }

        $transaction = Transaction::fromArray($data);
        $transaction->receivable = $this->receivables->findByTransactionId($id);

        return $transaction;
    }

    public function create(array $data): Transaction
    {
        [$transactionId, $receivableId] = $this->numerator->reserve(2);

        $transaction = Transaction::fromArray([
            ...$data,
            'id' => $transactionId,
            'cardNumber' => substr($data['cardNumber'], -4),
        ]);

        $this->store($transaction);

        try {
            $transaction->receivable = $this->receivables->createForTransaction($transaction, $receivableId);
        } catch (Throwable $e) {
            $this->rollback($transaction, $e);
        }

        return $transaction;
    }

    public function delete(string $id): void
    {
        $transaction = $this->find($id);

        if ($transaction->receivable !== null) {
            $this->receivables->deleteIfExists($transaction->receivable->id);
        }

        $this->client->destroy(self::RESOURCE, $id);
    }

    private function store(Transaction $transaction): void
    {
        try {
            $this->client->store(self::RESOURCE, $transaction->toArray());
        } catch (Throwable $e) {
            Log::critical('The transaction write failed; json-server may have stored it without a receivable.', [
                'transaction_id' => $transaction->id,
                'cause' => $e->getMessage(),
            ]);

            throw new TransactionCreationFailedException(
                "Transaction [{$transaction->id}] could not be created.",
                previous: $e,
            );
        }
    }

    private function rollback(Transaction $transaction, Throwable $e): never
    {
        try {
            $this->client->destroy(self::RESOURCE, $transaction->id);
        } catch (Throwable $rollbackFailure) {
            Log::critical('Receivable creation failed and the compensating delete failed too, leaving an orphan transaction.', [
                'transaction_id' => $transaction->id,
                'cause' => $e->getMessage(),
                'rollback_failure' => $rollbackFailure->getMessage(),
            ]);

            throw new TransactionCreationFailedException(
                "Transaction [{$transaction->id}] has no receivable and could not be rolled back.",
                previous: $e,
            );
        }

        throw new TransactionCreationFailedException(
            'The receivable could not be created, so the transaction was rolled back.',
            previous: $e,
        );
    }
}
