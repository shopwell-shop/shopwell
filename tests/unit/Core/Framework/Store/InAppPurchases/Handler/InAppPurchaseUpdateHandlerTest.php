<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\InAppPurchases\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseUpdateHandler;
use Shopwell\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdateHandler::class)]
class InAppPurchaseUpdateHandlerTest extends TestCase
{
    private InAppPurchaseUpdater&MockObject $iapUpdater;

    private LoggerInterface&MockObject $logger;

    private InAppPurchaseUpdateHandler $iapUpdateHandler;

    protected function setUp(): void
    {
        $this->iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->iapUpdateHandler = new InAppPurchaseUpdateHandler(
            static::createStub(EntityRepository::class),
            $this->logger,
            $this->iapUpdater,
        );
    }

    public function testRunWithActiveInAppPurchases(): void
    {
        $this->iapUpdater
            ->expects($this->once())
            ->method('update')
            ->with(Context::createCLIContext());

        $this->logger
            ->expects($this->never())
            ->method(static::anything());

        $this->iapUpdateHandler->run();
    }
}
