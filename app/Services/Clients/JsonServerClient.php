<?php

namespace App\Services\Clients;

use App\Exceptions\JsonServerException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class JsonServerClient
{
    public function index(string $resource, array $query = []): array
    {
        return $this->send('get', $resource, query: $query)->json() ?? [];
    }

    public function show(string $resource, string $id): ?array
    {
        $response = $this->send('get', "{$resource}/{$id}", allowNotFound: true);

        return $response->status() === 404 ? null : $response->json();
    }

    public function store(string $resource, array $payload): array
    {
        return $this->send('post', $resource, payload: $payload)->json() ?? [];
    }

    public function destroy(string $resource, string $id): bool
    {
        return $this->send('delete', "{$resource}/{$id}", allowNotFound: true)->status() !== 404;
    }

    private function send(string $method, string $path, array $query = [], array $payload = [], bool $allowNotFound = false): Response
    {
        $request = Http::baseUrl(config('services.json_server.base_url'))
            ->timeout((int) config('services.json_server.timeout'))
            ->acceptJson();

        try {
            $response = match ($method) {
                'get' => $request->get($path, $query),
                'post' => $request->post($path, $payload),
                'delete' => $request->delete($path),
            };
        } catch (ConnectionException $e) {
            throw new JsonServerException("json-server is unreachable ({$method} /{$path}).", previous: $e);
        }

        if ($response->failed() && !($allowNotFound && $response->status() === 404)) {
            throw new JsonServerException("json-server returned {$response->status()} for {$method} /{$path}.");
        }

        return $response;
    }
}
