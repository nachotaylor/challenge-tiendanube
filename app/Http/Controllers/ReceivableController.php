<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReceivableResource;
use App\Services\ReceivableService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReceivableController extends Controller
{
    public function __construct(private readonly ReceivableService $receivables)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return ReceivableResource::collection($this->receivables->list());
    }

    public function show(string $id): ReceivableResource
    {
        return new ReceivableResource($this->receivables->find($id));
    }

    public function destroy(string $id): Response
    {
        $this->receivables->delete($id);

        return response()->noContent();
    }
}
