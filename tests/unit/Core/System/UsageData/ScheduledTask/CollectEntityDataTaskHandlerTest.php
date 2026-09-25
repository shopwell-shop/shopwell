<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopwell\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopwell\Core\System\UsageData\ScheduledTask\CollectEntityDataTaskHandler;
use Shopwell\Core\System\UsageData\Services\EntityDispatchService;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataTaskHandler::class)]
class CollectEntityDataTaskHandlerTest extends TestCase
{
    public function testItStartsCollectingData(): void
    {
        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())
            ->method('dispatchCollectEntityDataMessage');

        /** @var StaticEntityRepository<ScheduledTaskCollection> */
        $repository = new StaticEntityRepository([], new ScheduledTaskDefinition());

        $taskHandler = new CollectEntityDataTaskHandler(
            $repository,
            static::createStub(LoggerInterface::class),
            $entityDispatchService
        );

        $taskHandler->run();
    }
}
