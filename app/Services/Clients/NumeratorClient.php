<?php

namespace App\Services\Clients;

use App\Exceptions\NumeratorConflictException;
use App\Exceptions\NumeratorUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class NumeratorClient
{
    public function getCurrent(): int
    {
        try {
            $response = $this->request()->get('/numerator');
        } catch (ConnectionException $e) {
            throw new NumeratorUnavailableException('numerator-api is unreachable (GET /numerator).', previous: $e);
        }

        if ($response->failed()) {
            throw new NumeratorUnavailableException("numerator-api returned {$response->status()} for GET /numerator.");
        }

        return (int) $response->json('numerator');
    }

    public function testAndSet(int $oldValue, int $newValue): int
    {
        try {
            $response = $this->request()->put('/numerator/test-and-set', [
                'oldValue' => $oldValue,
                'newValue' => $newValue,
            ]);
        } catch (ConnectionException $e) {
            throw new NumeratorUnavailableException('numerator-api is unreachable (PUT /numerator/test-and-set).', previous: $e);
        }

        if ($response->status() === 400 && $response->json('currentNumerator') !== null) {
            throw new NumeratorConflictException((int) $response->json('currentNumerator'));
        }

        if ($response->failed()) {
            throw new NumeratorUnavailableException("numerator-api returned {$response->status()} for PUT /numerator/test-and-set.");
        }

        return (int) $response->json('numerator');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('services.numerator.base_url'))
            ->timeout((int) config('services.numerator.timeout'))
            ->acceptJson();
    }
}
