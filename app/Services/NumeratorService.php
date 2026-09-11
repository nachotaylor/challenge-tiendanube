<?php

namespace App\Services;

use App\Exceptions\NumeratorConflictException;
use App\Exceptions\NumeratorUnavailableException;
use App\Services\Clients\JsonServerClient;
use App\Services\Clients\NumeratorClient;

class NumeratorService
{
    private bool $reconciled = false;

    public function __construct(
        private readonly NumeratorClient $client,
        private readonly JsonServerClient $jsonServer,
    ) {
    }

    public function reserve(int $count = 1): array
    {
        $maxAttempts = max(1, (int) config('services.numerator.max_attempts'));
        $backoffMs = (int) config('services.numerator.retry_backoff_ms');
        $current = $this->reconcile();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $last = $this->client->testAndSet($current, $current + $count);

                return array_map(strval(...), range($last - $count + 1, $last));
            } catch (NumeratorConflictException $conflict) {
                $current = $conflict->currentNumerator;

                if ($attempt < $maxAttempts) {
                    usleep($backoffMs * 1000);
                }
            }
        }

        throw new NumeratorUnavailableException("Could not reserve {$count} unique id(s) after {$maxAttempts} attempts due to contention.");
    }

    private function reconcile(): int
    {
        $current = $this->client->getCurrent();

        if ($this->reconciled) {
            return $current;
        }

        $this->reconciled = true;
        $highest = $this->highestStoredId();

        if ($highest <= $current) {
            return $current;
        }

        try {
            return $this->client->testAndSet($current, $highest);
        } catch (NumeratorConflictException $conflict) {
            return $conflict->currentNumerator;
        }
    }

    private function highestStoredId(): int
    {
        $ids = [0];

        foreach ((array) config('services.numerator.id_space') as $resource) {
            foreach ($this->jsonServer->index($resource) as $record) {
                $ids[] = (int) ($record['id'] ?? 0);
            }
        }

        return max($ids);
    }
}
