<?php

namespace App\Tests\Unit\Service;

use App\Entity\Edition;
use App\Entity\Work;
use App\Repository\CopyRepository;
use App\Repository\EditionRepository;
use App\Repository\LoanRepository;
use App\Service\Statistics\StatsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de StatsService (taux de rotation, top emprunts, documents dormants).
 */
class StatsServiceTest extends TestCase
{
    private StatsService $service;

    /** @var LoanRepository&MockObject */
    private LoanRepository $loanRepo;

    /** @var CopyRepository&MockObject */
    private CopyRepository $copyRepo;

    /** @var EditionRepository&MockObject */
    private EditionRepository $editionRepo;

    protected function setUp(): void
    {
        $this->loanRepo    = $this->createMock(LoanRepository::class);
        $this->copyRepo    = $this->createMock(CopyRepository::class);
        $this->editionRepo = $this->createMock(EditionRepository::class);

        $this->service = new StatsService(
            $this->loanRepo,
            $this->copyRepo,
            $this->editionRepo,
        );
    }

    public function testGetRotationRateCalculatesCorrectRatio(): void
    {
        $this->loanRepo->method('countTotalLoans')->willReturn(150);
        $this->copyRepo->method('countTotalCopies')->willReturn(100);

        $rate = $this->service->getRotationRate();

        $this->assertSame(1.5, $rate);
    }

    public function testGetRotationRateReturnsZeroWhenNoCopies(): void
    {
        $this->loanRepo->method('countTotalLoans')->willReturn(0);
        $this->copyRepo->method('countTotalCopies')->willReturn(0);

        $rate = $this->service->getRotationRate();

        $this->assertSame(0.0, $rate);
    }

    public function testGetTopBorrowedEditionsDelegatesToRepository(): void
    {
        $edition = (new Edition())->setWork((new Work())->setTitle('1984'));
        $expected = [
            ['edition' => $edition, 'loanCount' => 12],
        ];

        $this->editionRepo->expects($this->once())
            ->method('getTopBorrowedEditions')
            ->with(5)
            ->willReturn($expected);

        $result = $this->service->getTopBorrowedEditions(5);

        $this->assertSame($expected, $result);
    }

    public function testGetDormantEditionsDelegatesToRepository(): void
    {
        $dormantEdition = (new Edition())->setWork((new Work())->setTitle('Ouvrage Oublié'));

        $this->editionRepo->expects($this->once())
            ->method('findDormantEditions')
            ->with(20)
            ->willReturn([$dormantEdition]);

        $result = $this->service->getDormantEditions(20);

        $this->assertCount(1, $result);
        $this->assertSame($dormantEdition, $result[0]);
    }

    public function testGetDashboardDataAggregatesAllMetrics(): void
    {
        $this->loanRepo->method('countTotalLoans')->willReturn(80);
        $this->copyRepo->method('countTotalCopies')->willReturn(40);
        $this->editionRepo->method('getTopBorrowedEditions')->willReturn([]);
        $this->editionRepo->method('findDormantEditions')->willReturn([]);

        $data = $this->service->getDashboardData();

        $this->assertSame(80, $data['totalLoans']);
        $this->assertSame(40, $data['totalCopies']);
        $this->assertSame(2.0, $data['rotationRate']);
        $this->assertArrayHasKey('topBorrowed', $data);
        $this->assertArrayHasKey('dormant', $data);
    }
}
