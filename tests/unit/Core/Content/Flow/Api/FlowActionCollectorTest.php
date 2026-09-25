<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Api\FlowActionCollector;
use Shopwell\Core\Content\Flow\Api\FlowActionDefinition;
use Shopwell\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopwell\Core\Content\Flow\Dispatching\Action\RemoveOrderTagAction;
use Shopwell\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(FlowActionCollector::class)]
class FlowActionCollectorTest extends TestCase
{
    public function testCollect(): void
    {
        $addCustomerTag = new AddCustomerTagAction(static::createStub(EntityRepository::class));
        $removeOrderTag = new RemoveOrderTagAction(static::createStub(EntityRepository::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $appFlowActionRepo = $this->createMock(EntityRepository::class);
        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(new EntityCollection([
                (new AppFlowActionEntity())->assign([
                    'id' => Uuid::randomHex(),
                    'name' => 'slack.app',
                    'requirements' => ['orderAware'],
                    'delayable' => false,
                ]),
            ]));

        $appFlowActionRepo->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $flowActionCollector = new FlowActionCollector(
            [$addCustomerTag, $removeOrderTag],
            $eventDispatcher,
            $appFlowActionRepo
        );

        $result = $flowActionCollector->collect(Context::createDefaultContext());

        $customerRequirements = [];
        $customerRequirements[] = 'customerAware';

        $orderRequirements = [];
        $orderRequirements[] = 'orderAware';

        static::assertEquals(
            [
                AddCustomerTagAction::getName() => new FlowActionDefinition(
                    AddCustomerTagAction::getName(),
                    $customerRequirements,
                    true
                ),
                RemoveOrderTagAction::getName() => new FlowActionDefinition(
                    RemoveOrderTagAction::getName(),
                    $orderRequirements,
                    true
                ),
                'slack.app' => new FlowActionDefinition(
                    'slack.app',
                    ['orderAware'],
                    false
                ),
            ],
            $result->getElements()
        );
    }
}
