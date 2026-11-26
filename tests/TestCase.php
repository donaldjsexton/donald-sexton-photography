<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // CI runs tests before the frontend build, so there is no Vite manifest.
        $this->withoutVite();
    }
}
