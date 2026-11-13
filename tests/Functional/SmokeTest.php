<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SmokeTest extends KernelTestCase
{
    public function testKernelBootsInTestEnvironment(): void
    {
        self::bootKernel();

        self::assertSame('test', self::$kernel->getEnvironment());
    }

    public function testDatabaseIsReachable(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine')->getConnection();

        self::assertSame(1, (int) $connection->fetchOne('SELECT 1'));
    }
}