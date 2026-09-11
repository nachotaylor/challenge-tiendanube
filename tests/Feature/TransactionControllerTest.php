<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    public function test_it_creates_a_transaction_and_returns_the_derived_receivable(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions' => Http::response([], 201),
            '*8080/receivables' => Http::response([], 201),
        ]);

        $response = $this->postJson('/api/transactions', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.id', '4')
            ->assertJsonPath('data.cardNumber', '2222')
            ->assertJsonPath('data.receivable.id', '5')
            ->assertJsonPath('data.receivable.status', 'waiting_funds')
            ->assertJsonPath('data.receivable.total', '240.00');
    }

    public function test_it_stores_and_returns_every_field_the_brief_defines(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions' => Http::response([], 201),
            '*8080/receivables' => Http::response([], 201),
        ]);

        // Only the card number is masked; the brief lists every other field, cardCvv included.
        $this->postJson('/api/transactions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.cardCvv', '222')
            ->assertJsonPath('data.cardNumber', '2222');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8080/transactions'
            && array_keys($request->data()) === ['id', 'value', 'description', 'method',
                'cardNumber', 'cardHolderName', 'cardExpirationDate', 'cardCvv']);
    }

    public function test_it_rejects_an_invalid_payload(): void
    {
        $response = $this->postJson('/api/transactions', [...$this->payload(), 'method' => 'cash', 'cardExpirationDate' => '2028-04']);

        $response->assertStatus(422)->assertJsonValidationErrors(['method', 'cardExpirationDate']);
    }

    public function test_it_returns_503_when_the_numerator_is_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->postJson('/api/transactions', $this->payload())->assertStatus(503);

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '8080'));
    }

    public function test_it_returns_502_when_the_receivable_creation_fails(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions/*' => Http::response([], 200),
            // The first call to each collection is the reconciliation listing; the second is the write.
            '*8080/transactions' => Http::sequence()->push([])->push([], 201),
            '*8080/receivables' => Http::sequence()->push([])->push(['error' => 'down'], 500),
        ]);

        $this->postJson('/api/transactions', $this->payload())->assertStatus(502);

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/transactions/4');
    }

    public function test_it_masks_card_numbers_stored_by_other_clients(): void
    {
        // json-server accepts writes from anyone, so a full number can already be in there.
        Http::fake([
            '*8080/transactions' => Http::response([
                $this->storedTransaction() + ['cardNumber' => '4111111111113486'],
            ]),
            '*8080/receivables' => Http::response([]),
        ]);

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.cardNumber', '3486');
    }

    public function test_it_embeds_the_receivable_when_listing_transactions(): void
    {
        Http::fake([
            '*8080/transactions' => Http::response([$this->storedTransaction()]),
            '*8080/receivables' => Http::response([$this->storedReceivable()]),
        ]);

        $this->getJson('/api/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.receivable.id', '3')
            ->assertJsonPath('data.0.receivable.total', '96');

        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->url() === 'http://localhost:8080/receivables'));
    }

    public function test_it_shows_a_single_transaction(): void
    {
        Http::fake([
            '*8080/transactions/1' => Http::response($this->storedTransaction()),
            '*8080/receivables*' => Http::response([]),
        ]);

        $this->getJson('/api/transactions/1')->assertOk()->assertJsonPath('data.description', 'T-Shirt Black/M');
    }

    public function test_it_embeds_the_receivable_when_showing_a_transaction(): void
    {
        Http::fake([
            '*8080/transactions/1' => Http::response($this->storedTransaction()),
            '*8080/receivables*' => Http::response([$this->storedReceivable()]),
        ]);

        $this->getJson('/api/transactions/1')
            ->assertOk()
            ->assertJsonPath('data.receivable.id', '3')
            ->assertJsonPath('data.receivable.total', '96');
    }

    public function test_it_returns_404_for_an_unknown_transaction(): void
    {
        Http::fake(['*8080/transactions/99' => Http::response([], 404)]);

        $this->getJson('/api/transactions/99')->assertNotFound();
    }

    public function test_it_returns_502_for_a_record_it_cannot_read(): void
    {
        Http::fake(['*8080/transactions' => Http::response([
            ['id' => '1', 'value' => '100', 'description' => 'x', 'method' => 'bitcoin',
                'cardNumber' => '3486', 'cardHolderName' => 'x', 'cardExpirationDate' => '04/28', 'cardCvv' => '290'],
        ])]);

        $this->getJson('/api/transactions')->assertStatus(502);
    }

    public function test_it_deletes_a_transaction_together_with_its_receivable(): void
    {
        Http::fake([
            '*8080/transactions/*' => Http::sequence()->push($this->storedTransaction())->push([]),
            '*8080/receivables*' => Http::sequence()
                ->push([$this->storedReceivable()])
                ->push([]),
        ]);

        $this->deleteJson('/api/transactions/1')->assertNoContent();

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/receivables/3');
    }

    public function test_it_returns_404_when_deleting_an_unknown_transaction(): void
    {
        Http::fake(['*8080/transactions/99' => Http::response([], 404)]);

        $this->deleteJson('/api/transactions/99')->assertNotFound();
    }

    private function fakeNumerator(int $current, int $reserved): void
    {
        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => $current]),
            '*3000/numerator/test-and-set' => Http::response(['numerator' => $reserved]),
        ]);
    }

    private function payload(): array
    {
        return [
            'value' => '250.00',
            'description' => 'T-Shirt',
            'method' => 'credit_card',
            'cardNumber' => '4111111111112222',
            'cardHolderName' => 'Simplenube Store',
            'cardExpirationDate' => '04/28',
            'cardCvv' => '222',
        ];
    }

    private function storedTransaction(): array
    {
        return [
            'id' => '1',
            'value' => '100',
            'description' => 'T-Shirt Black/M',
            'method' => 'credit_card',
            'cardNumber' => '3486',
            'cardHolderName' => 'Fonsi Julian',
            'cardExpirationDate' => '04/28',
            'cardCvv' => '290',
        ];
    }

    private function storedReceivable(): array
    {
        return [
            'id' => '3',
            'transaction_id' => '1',
            'status' => 'waiting_funds',
            'create_date' => '15/03/2020',
            'subtotal' => '100',
            'discount' => '4',
            'total' => '96',
        ];
    }
}
