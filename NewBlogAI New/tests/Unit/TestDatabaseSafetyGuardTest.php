<?php

namespace Tests\Unit;

use LogicException;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Tests\TestCase;

class TestDatabaseSafetyGuardTest extends PHPUnitTestCase
{
    public function test_it_accepts_the_in_memory_sqlite_test_database(): void
    {
        TestCase::assertSafeTestingDatabase('sqlite', ':memory:');

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_a_non_test_database(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Unsafe test database configuration');

        TestCase::assertSafeTestingDatabase('mysql', 'newsblogify');
    }
}
