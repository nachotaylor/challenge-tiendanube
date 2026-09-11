<?php

namespace Tests\Unit\Services;

use App\Exceptions\NumeratorUnavailableException;
use App\Services\NumeratorService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NumeratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.numerator.retry_backoff_ms' => 0]);
    }

    public function test_it_reserves_a_contiguous_block_with_a_single_test_and_set(): void
    {
        $this->fakeStoredIds(transactions: ['1', '2', '3']);

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 3]),
            '*3000/numerator/test-and-set' => Http::response(['numerator' => 5]),
        ]);

        $this->assertSame(['4', '5'], app(NumeratorService::class)->reserve(2));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'test-and-set')
            && $request['oldValue'] === 3
            && $request['newValue'] === 5);

        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), 'test-and-set')));
    }

    public function test_it_pushes_the_counter_past_ids_that_already_exist(): void
    {
        // The numerator restarted back to 3 while json-server kept records up to 7.
        $this->fakeStoredIds(transactions: ['6', '4'], receivables: ['7', '5']);

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 3]),
            '*3000/numerator/test-and-set' => Http::sequence()
                ->push(['numerator' => 7])
                ->push(['numerator' => 8]),
        ]);

        $this->assertSame(['8'], app(NumeratorService::class)->reserve());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'test-and-set')
            && $request['oldValue'] === 3
            && $request['newValue'] === 7);
    }

    public function test_it_leaves_the_counter_alone_when_it_is_already_ahead(): void
    {
        $this->fakeStoredIds(transactions: ['1', '2']);

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 9]),
            '*3000/numerator/test-and-set' => Http::response(['numerator' => 10]),
        ]);

        $this->assertSame(['10'], app(NumeratorService::class)->reserve());

        $this->assertCount(1, Http::recorded(fn (Request $request) => str_contains($request->url(), 'test-and-set')));
    }

    public function test_it_inspects_the_stored_ids_only_once_per_instance(): void
    {
        $this->fakeStoredIds(transactions: ['1']);

        Http::fake([
            '*3000/numerator' => Http::sequence()->push(['numerator' => 3])->push(['numerator' => 4]),
            '*3000/numerator/test-and-set' => Http::sequence()->push(['numerator' => 4])->push(['numerator' => 5]),
        ]);

        $service = app(NumeratorService::class);
        $service->reserve();
        $service->reserve();

        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->url() === 'http://localhost:8080/transactions'));
    }

    public function test_it_retries_with_the_numerator_returned_by_a_conflicting_test_and_set(): void
    {
        $this->fakeStoredIds();

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 3]),
            '*3000/numerator/test-and-set' => Http::sequence()
                ->push(['error' => 'Numerator does not match the expected old value.', 'currentNumerator' => 7], 400)
                ->push(['numerator' => 8]),
        ]);

        $this->assertSame(['8'], app(NumeratorService::class)->reserve());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'test-and-set')
            && $request['oldValue'] === 7
            && $request['newValue'] === 8);
    }

    public function test_it_fails_when_contention_exhausts_every_attempt(): void
    {
        config(['services.numerator.max_attempts' => 2]);
        $this->fakeStoredIds();

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 3]),
            '*3000/numerator/test-and-set' => Http::response(['currentNumerator' => 9], 400),
        ]);

        $this->expectException(NumeratorUnavailableException::class);

        app(NumeratorService::class)->reserve();
    }

    public function test_it_always_makes_at_least_one_attempt(): void
    {
        config(['services.numerator.max_attempts' => 0]);
        $this->fakeStoredIds();

        Http::fake([
            '*3000/numerator' => Http::response(['numerator' => 3]),
            '*3000/numerator/test-and-set' => Http::response(['numerator' => 4]),
        ]);

        $this->assertSame(['4'], app(NumeratorService::class)->reserve());
    }

    public function test_it_fails_when_the_numerator_api_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(NumeratorUnavailableException::class);

        app(NumeratorService::class)->reserve();
    }

    public function test_it_asks_the_numerator_before_reading_json_server(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        try {
            app(NumeratorService::class)->reserve();
        } catch (NumeratorUnavailableException) {
            // expected
        }

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '8080'));
    }

    private function fakeStoredIds(array $transactions = [], array $receivables = []): void
    {
        $records = fn (array $ids) => array_map(fn (string $id) => ['id' => $id], $ids);

        Http::fake([
            '*8080/transactions' => Http::response($records($transactions)),
            '*8080/receivables' => Http::response($records($receivables)),
        ]);
    }
}
