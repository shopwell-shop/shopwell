<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\UsageData\EntitySync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Shopwell\Core\System\UsageData\EntitySync\CollectEntityDataMessageHandler;
use Shopwell\Core\System\UsageData\Services\EntityDispatchService;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataMessageHandler::class)]
class CollectEntityDataMessageHandlerTest extends TestCase
{
    public function testInvoke(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())
            ->method('dispatchIterateEntityMessages');

        $messageHandler = new CollectEntityDataMessageHandler($entityDispatchService);
        $messageHandler(new CollectEntityDataMessage());
    }
}
