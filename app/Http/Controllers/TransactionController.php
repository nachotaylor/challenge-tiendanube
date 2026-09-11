<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $transactions)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return TransactionResource::collection($this->transactions->list());
    }

    public function show(string $id): TransactionResource
    {
        return new TransactionResource($this->transactions->find($id));
    }

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        return (new TransactionResource($this->transactions->create($request->validated())))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(string $id): Response
    {
        $this->transactions->delete($id);

        return response()->noContent();
    }
}
