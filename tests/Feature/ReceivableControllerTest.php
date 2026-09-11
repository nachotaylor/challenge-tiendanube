<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceivableControllerTest extends TestCase
{
    public function test_it_lists_receivables(): void
    {
        Http::fake(['*8080/receivables' => Http::response([$this->storedReceivable()])]);

        $this->getJson('/api/receivables')
            ->assertOk()
            ->assertJsonPath('data.0.id', '3')
            ->assertJsonPath('data.0.total', '96');
    }

    public function test_it_returns_the_receivable_exactly_as_the_brief_defines_it(): void
    {
        Http::fake(['*8080/receivables' => Http::response([$this->storedReceivable()])]);

        $this->getJson('/api/receivables')
            ->assertOk()
            ->assertJsonPath('data.0', $this->storedReceivable());
    }

    public function test_it_shows_a_single_receivable(): void
    {
        Http::fake(['*8080/receivables/3' => Http::response($this->storedReceivable())]);

        $this->getJson('/api/receivables/3')->assertOk()->assertJsonPath('data.status', 'waiting_funds');
    }

    public function test_it_returns_404_for_an_unknown_receivable(): void
    {
        Http::fake(['*8080/receivables/99' => Http::response([], 404)]);

        $this->getJson('/api/receivables/99')->assertNotFound();
    }

    public function test_it_deletes_a_receivable(): void
    {
        Http::fake(['*8080/receivables/3' => Http::response([])]);

        $this->deleteJson('/api/receivables/3')->assertNoContent();
    }

    public function test_it_returns_404_when_deleting_an_unknown_receivable(): void
    {
        Http::fake(['*8080/receivables/99' => Http::response([], 404)]);

        $this->deleteJson('/api/receivables/99')->assertNotFound();
    }

    public function test_receivables_cannot_be_created_on_their_own(): void
    {
        $this->postJson('/api/receivables', [])->assertStatus(405);
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
