<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Without this, a request matching no fake pattern goes out over the real network and
        // would mutate the provided json-server seed data whenever the containers are running.
        Http::preventStrayRequests();
    }
}
