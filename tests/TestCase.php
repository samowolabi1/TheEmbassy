<?php

namespace Tests;

use App\Models\User;
use App\Services\Tokens\Ledger;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Put tokens in the church treasury, as the pastor in charge would, so rewards can be paid.
     */
    protected function fundTreasury(int $tokens = 100_000): void
    {
        app(Ledger::class)->mint($tokens, User::factory()->superAdmin()->create(), 'Test tokens');
    }
}
