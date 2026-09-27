<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::set(null);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);

        parent::tearDown();
    }
}
