<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\BusinessEventEncoder;
use Shopwell\Core\Framework\Webhook\Hookable;
use Shopwell\Core\Framework\Webhook\Hookable\HookableBusinessEvent;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEventFactory;
use Shopwell\Core\Framework\Webhook\Hookable\WriteResultMerger;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HookableEventFactory::class)]
class HookableEventFactoryTest extends TestCase
{
    private HookableEventFactory $hookableEventFactory;

    private BusinessEventEncoder $businessEventEncoder;

    private WriteResultMerger $writeResultMerger;

    private HookableEventCollector $hookableEventCollector;

    protected function setUp(): void
    {
        $this->businessEventEncoder = static::createStub(BusinessEventEncoder::class);
        $this->writeResultMerger = static::createStub(WriteResultMerger::class);
        $this->hookableEventCollector = static::createStub(HookableEventCollector::class);

        $this->hookableEventFactory = new HookableEventFactory(
            $this->businessEventEncoder,
            $this->writeResultMerger,
            $this->hookableEventCollector
        );
    }

    public function testCreateHookablesForStorableFlow(): void
    {
        $flow = new StorableFlow('foo', Context::createDefaultContext());

        $result = $this->hookableEventFactory->createHookablesFor($flow);

        static::assertSame([], $result);
    }

    public function testCreateHookablesForHookableEvent(): void
    {
        $hookableEvent = static::createStub(Hookable::class);

        $result = $this->hookableEventFactory->createHookablesFor($hookableEvent);

        static::assertSame([$hookableEvent], $result);
    }

    public function testCreateHookablesForFlowEventAware(): void
    {
        $flowEventAware = static::createStub(FlowEventAware::class);

        $result = $this->hookableEventFactory->createHookablesFor($flowEventAware);

        static::assertCount(1, $result);
        static::assertInstanceOf(HookableBusinessEvent::class, $result[0]);
    }

    public function testCreateHookablesForEntityWrittenContainerEvent(): void
    {
        $entityWrittenEvent = $this->createEntityWrittenEvent();
        $mergedWrittenEvent = $this->createEntityWrittenEvent();

        $hookableEventCollector = static::createStub(HookableEventCollector::class);
        $hookableEventCollector->method('getHookableEntities')->willReturn(['product', 'customer']);

        $writeResultMerger = static::createStub(WriteResultMerger::class);
        $writeResultMerger->method('mergeWriteResults')->willReturn($mergedWrittenEvent);

        $hookableEventFactory = new HookableEventFactory(
            $this->businessEventEncoder,
            $writeResultMerger,
            $hookableEventCollector
        );

        $entityWrittenContainerEvent = static::createStub(EntityWrittenContainerEvent::class);
        $entityWrittenContainerEvent
            ->method('getEventByEntityName')
            ->willReturnCallback(static function (string $entityName) use ($entityWrittenEvent) {
                return match ($entityName) {
                    'product' => $entityWrittenEvent,
                    'product_translation' => null,
                    'customer' => null,
                    'customer_translation' => null,
                    default => null,
                };
            });

        $result = $hookableEventFactory->createHookablesFor($entityWrittenContainerEvent);

        static::assertCount(1, $result);
        static::assertInstanceOf(HookableEntityWrittenEvent::class, $result[0]);
    }

    public function testCreateHookablesForNonHookableEvent(): void
    {
        $nonHookableEvent = new \stdClass();

        $result = $this->hookableEventFactory->createHookablesFor($nonHookableEvent);

        static::assertSame([], $result);
    }

    private function createEntityWrittenEvent(): EntityWrittenEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenEvent(
            ProductDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    Uuid::randomHex(),
                    [],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT,
                    null,
                    null
                ),
            ],
            $context
        );
    }
}
