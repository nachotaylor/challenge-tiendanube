<?php

namespace Tests\Unit\Services;

use App\Exceptions\TransactionCreationFailedException;
use App\Services\TransactionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    public function test_it_creates_a_transaction_with_its_receivable_and_masks_the_card_number(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions' => Http::response([], 201),
            '*8080/receivables' => Http::response([], 201),
        ]);

        $transaction = app(TransactionService::class)->create($this->payload());

        $this->assertSame('4', $transaction->id);
        $this->assertSame('2222', $transaction->cardNumber);
        $this->assertSame('5', $transaction->receivable->id);
        $this->assertSame('4', $transaction->receivable->transaction_id);
        $this->assertSame('240.00', $transaction->receivable->total);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8080/transactions'
            && $request['id'] === '4'
            && $request['cardNumber'] === '2222');
    }

    public function test_it_reserves_both_ids_before_writing_anything(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions' => Http::response([], 201),
            '*8080/receivables' => Http::response([], 201),
        ]);

        app(TransactionService::class)->create($this->payload());

        // A single test-and-set covers both ids, so no id is reserved after the first write.
        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), 'test-and-set')));
    }

    public function test_it_rolls_the_transaction_back_when_the_receivable_cannot_be_created(): void
    {
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions/*' => Http::response([], 200),
            // The first call to each collection is the reconciliation listing; the second is the write.
            '*8080/transactions' => Http::sequence()->push([])->push([], 201),
            '*8080/receivables' => Http::sequence()->push([])->push(['error' => 'down'], 500),
        ]);

        try {
            app(TransactionService::class)->create($this->payload());
            $this->fail('A TransactionCreationFailedException was expected.');
        } catch (TransactionCreationFailedException $e) {
            $this->assertStringContainsString('rolled back', $e->getMessage());
        }

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/transactions/4');
    }

    public function test_it_reports_an_orphan_when_the_compensating_delete_also_fails(): void
    {
        Log::spy();
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions/*' => Http::response(['error' => 'down'], 500),
            '*8080/transactions' => Http::sequence()->push([])->push([], 201),
            '*8080/receivables' => Http::sequence()->push([])->push(['error' => 'down'], 500),
        ]);

        try {
            app(TransactionService::class)->create($this->payload());
            $this->fail('A TransactionCreationFailedException was expected.');
        } catch (TransactionCreationFailedException $e) {
            $this->assertStringContainsString('could not be rolled back', $e->getMessage());
        }

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'orphan transaction')
                && $context['transaction_id'] === '4');
    }

    public function test_it_reports_a_possible_orphan_when_the_transaction_write_fails(): void
    {
        Log::spy();
        $this->fakeNumerator(current: 3, reserved: 5);

        Http::fake([
            '*8080/transactions' => Http::sequence()->push([])->push(['error' => 'down'], 500),
            '*8080/receivables' => Http::response([]),
        ]);

        try {
            app(TransactionService::class)->create($this->payload());
            $this->fail('A TransactionCreationFailedException was expected.');
        } catch (TransactionCreationFailedException) {
            // expected
        }

        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context) => $context['transaction_id'] === '4');

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8080/receivables');
    }

    public function test_it_deletes_the_associated_receivable_before_the_transaction(): void
    {
        Http::fake([
            '*8080/transactions/*' => Http::sequence()
                ->push($this->payload() + ['id' => '4'])
                ->push([]),
            '*8080/receivables*' => Http::sequence()
                ->push([$this->storedReceivable()])
                ->push([]),
        ]);

        app(TransactionService::class)->delete('4');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/receivables/9');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/transactions/4');
    }

    public function test_the_cascade_tolerates_a_receivable_that_vanished_first(): void
    {
        Http::fake([
            '*8080/transactions/*' => Http::sequence()
                ->push($this->payload() + ['id' => '4'])
                ->push([]),
            '*8080/receivables*' => Http::sequence()
                ->push([$this->storedReceivable()])
                ->push([], 404),
        ]);

        app(TransactionService::class)->delete('4');

        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE'
            && $request->url() === 'http://localhost:8080/transactions/4');
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

    private function storedReceivable(): array
    {
        return [
            'id' => '9',
            'transaction_id' => '4',
            'status' => 'waiting_funds',
            'create_date' => '2026-09-07T10:00:00.000+00:00',
            'subtotal' => '250.00',
            'discount' => '4',
            'total' => '240.00',
        ];
    }
}
