<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentMethod;
use App\Enums\ReceivableStatus;
use App\Models\Transaction;
use App\Services\ReceivableService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceivableServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-07T10:00:00+00:00');

        // Pinned so a developer's own .env cannot change what these assertions mean.
        config([
            'services.fees.debit_card' => '2',
            'services.fees.credit_card' => '4',
        ]);

        Http::fake(['*8080/receivables' => Http::response([], 201)]);
    }

    public function test_a_debit_card_receivable_is_paid_the_same_day_with_a_two_percent_fee(): void
    {
        $receivable = $this->create('340.50', PaymentMethod::DebitCard);

        $this->assertSame('10', $receivable->id);
        $this->assertSame('1', $receivable->transaction_id);
        $this->assertSame(ReceivableStatus::Paid, $receivable->status);
        $this->assertSame('340.50', $receivable->subtotal);
        $this->assertSame('2', $receivable->discount);
        $this->assertSame('333.69', $receivable->total);
    }

    public function test_a_credit_card_receivable_waits_for_funds_with_a_four_percent_fee(): void
    {
        $receivable = $this->create('100', PaymentMethod::CreditCard);

        $this->assertSame(ReceivableStatus::WaitingFunds, $receivable->status);
        $this->assertSame('100.00', $receivable->subtotal);
        $this->assertSame('4', $receivable->discount);
        $this->assertSame('96.00', $receivable->total);
    }

    /**
     * The payment date (D+0 / D+30) is derived from these two, never stored: the brief's
     * receivable schema has no field for it.
     */
    public function test_the_creation_date_is_now_for_both_payment_methods(): void
    {
        $this->assertSame('2026-09-07T10:00:00.000+00:00', $this->create('100', PaymentMethod::DebitCard)->create_date);
        $this->assertSame('2026-09-07T10:00:00.000+00:00', $this->create('100', PaymentMethod::CreditCard)->create_date);
    }

    /**
     * Exactly half a cent: truncation would give 0.00 and half-up gives 0.01.
     */
    public function test_a_fee_of_exactly_half_a_cent_rounds_up(): void
    {
        $this->assertSame('0.24', $this->create('0.25', PaymentMethod::DebitCard)->total);
        $this->assertSame('0.73', $this->create('0.75', PaymentMethod::DebitCard)->total);
    }

    /**
     * A value with a single decimal place is normalised to two before anything else.
     */
    public function test_a_value_with_one_decimal_place_is_normalised(): void
    {
        $receivable = $this->create('340.5', PaymentMethod::DebitCard);

        $this->assertSame('340.50', $receivable->subtotal);
        $this->assertSame('333.69', $receivable->total);
    }

    public function test_it_persists_the_receivable_on_json_server(): void
    {
        $this->create('250.00', PaymentMethod::CreditCard);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'http://localhost:8080/receivables'
            && $request['id'] === '10'
            && $request['status'] === 'waiting_funds'
            && $request['create_date'] === '2026-09-07T10:00:00.000+00:00'
            && $request['total'] === '240.00'
            // The stored record keeps exactly the fields the brief defines.
            && array_keys($request->data()) === ['id', 'transaction_id', 'status', 'create_date', 'subtotal', 'discount', 'total']);
    }

    private function create(string $value, PaymentMethod $method)
    {
        return app(ReceivableService::class)->createForTransaction(
            new Transaction(
                id: '1',
                value: $value,
                description: 'T-Shirt',
                method: $method,
                cardNumber: '2222',
                cardHolderName: 'Simplenube Store',
                cardExpirationDate: '04/28',
                cardCvv: '222',
            ),
            '10',
        );
    }
}
