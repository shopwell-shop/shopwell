<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopwell\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber;
use Shopwell\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewSubscriber::class)]
class ProductReviewSubscriberTest extends TestCase
{
    private ProductReviewCountService&Stub $productReviewCountService;

    private ProductReviewSubscriber $productReviewSubscriber;

    private StaticDefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->productReviewCountService = static::createStub(ProductReviewCountService::class);
        $this->productReviewSubscriber = $this->createSubscriber();

        $this->definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [ProductReviewDefinition::class, ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            'product_review.written' => 'createReview',
            EntityDeleteEvent::class => 'detectChangeset',
            'product_review.deleted' => 'onReviewDeleted',
        ], $this->productReviewSubscriber->getSubscribedEvents());
    }

    public function testDetectChangesetWithReviewDeleteEvent(): void
    {
        $ids = new IdsCollection();

        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $this->definitionInstanceRegistry->get(ProductReviewDefinition::class),
                    [
                        'id' => $ids->getBytes('foo'),
                    ],
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => $ids->get('foo')], true, false, false, [])
                ),
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->productReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertTrue($command->requiresChangeSet());
        }
    }

    public function testDetectChangesetWithInvalidCommands(): void
    {
        $ids = new IdsCollection();

        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $this->definitionInstanceRegistry->get(ProductDefinition::class),
                    [
                        'id' => $ids->getBytes('foo'),
                    ],
                    new EntityExistence(ProductDefinition::ENTITY_NAME, ['id' => $ids->get('foo')], true, false, false, [])
                ),
                new InsertCommand(
                    $this->definitionInstanceRegistry->get(ProductReviewDefinition::class),
                    ['id' => $ids->getBytes('foo')],
                    ['id' => $ids->getBytes('foo')],
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => $ids->get('foo')], true, false, false, []),
                    '/bar'
                ),
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->productReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }
    }

    public function testOnReviewDeleted(): void
    {
        $event = new EntityDeletedEvent(
            ProductReviewDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet(['customer_id' => 'customer_id'], [], true)
                ),
                // should not trigger update as it has empty changeset
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet([], [], true)
                ),
                // should not trigger update as it has wrong entity
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet(['customer_id' => 'customer_id'], [], true)
                ),
            ],
            Context::createDefaultContext(),
        );

        $productReviewCountService = $this->createMock(ProductReviewCountService::class);
        $productReviewCountService->expects($this->once())
            ->method('updateReviewCountForCustomer')
            ->with('customer_id');

        $this->createSubscriber($productReviewCountService)->onReviewDeleted($event);
    }

    public function testCreateReviewWithInvalidEntityName(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $productReviewCountService = $this->createMock(ProductReviewCountService::class);
        $productReviewCountService->expects($this->never())->method('updateReviewCount');
        $this->createSubscriber($productReviewCountService)->createReview($this->getEntityWrittenEvent($ids, true));
    }

    public function testCreateReview(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $productReviewCountService = $this->createMock(ProductReviewCountService::class);
        $productReviewCountService->expects($this->once())->method('updateReviewCount')->with($ids);

        $this->createSubscriber($productReviewCountService)->createReview($this->getEntityWrittenEvent($ids));
    }

    private function createSubscriber(?ProductReviewCountService $productReviewCountService = null): ProductReviewSubscriber
    {
        return new ProductReviewSubscriber($productReviewCountService ?? $this->productReviewCountService);
    }

    /**
     * @param string[] $ids
     */
    private function getEntityWrittenEvent(array $ids = [], bool $invalidEntity = false): EntityWrittenEvent
    {
        $entity = $invalidEntity ? ProductDefinition::ENTITY_NAME : ProductReviewDefinition::ENTITY_NAME;

        $writtenResults = [];
        foreach ($ids as $id) {
            $writtenResult = static::createStub(EntityWriteResult::class);
            $writtenResult->method('getPrimaryKey')->willReturn($id);
            $writtenResults[] = $writtenResult;
        }

        return new EntityWrittenEvent($entity, $writtenResults, Context::createDefaultContext());
    }
}
