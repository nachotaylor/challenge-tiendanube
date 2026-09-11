<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Transaction $resource
 */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'value' => $this->resource->value,
            'description' => $this->resource->description,
            'method' => $this->resource->method->value,
            'cardNumber' => substr($this->resource->cardNumber, -4),
            'cardHolderName' => $this->resource->cardHolderName,
            'cardExpirationDate' => $this->resource->cardExpirationDate,
            'cardCvv' => $this->resource->cardCvv,
            'receivable' => $this->when(
                $this->resource->receivable !== null,
                fn () => new ReceivableResource($this->resource->receivable),
            ),
        ];
    }
}
