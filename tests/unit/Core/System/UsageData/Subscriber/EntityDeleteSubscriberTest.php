<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Consent\ConsentScope;
use Shopwell\Core\System\Consent\ConsentStatus;
use Shopwell\Core\System\Consent\Definition\BackendData;
use Shopwell\Core\System\Consent\DTO\ConsentState;
use Shopwell\Core\System\Consent\Service\ConsentService;
use Shopwell\Core\System\UsageData\Services\EntityDefinitionService;
use Shopwell\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopwell\Core\System\UsageData\Subscriber\EntityDeleteSubscriber;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(EntityDeleteSubscriber::class)]
class EntityDeleteSubscriberTest extends TestCase
{
    private UsageDataAllowListService $usageDataAllowListServiceMock;

    /**
     * @var array<string, bool>
     */
    private array $requiredParameter = [
        ':entity_name' => false,
        ':entity_ids' => false,
    ];

    protected function setUp(): void
    {
        $usageDataAllowListServiceMock = static::createStub(UsageDataAllowListService::class);
        $usageDataAllowListServiceMock->method('isEntityAllowed')
            ->willReturn(true);
        $usageDataAllowListServiceMock->method('getFieldsToSelectFromDefinition')
            ->willReturnCallback(static function (EntityDefinition $definition) {
                return $definition->getFields();
            });

        $this->usageDataAllowListServiceMock = $usageDataAllowListServiceMock;
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            EntityDeleteEvent::class => 'handleEntityDeleteEvent',
        ], EntityDeleteSubscriber::getSubscribedEvents());
    }

    public function testHandleDeletedEventStoresData(): void
    {
        $productId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func());

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects($this->exactly(4))
            ->method('bindValue')
            ->withAnyParameters()
            ->willReturnCallback(function ($key, $value) use ($productId): void {
                if ($key === ':entity_name') {
                    static::assertSame(EntityWithSinglePrimaryKey::ENTITY_NAME, $value);
                    $this->requiredParameter[':entity_name'] = true;
                }

                if ($key === ':entity_ids') {
                    static::assertSame(json_encode(['id' => Uuid::fromBytesToHex($productId)]), $value);
                    $this->requiredParameter[':entity_ids'] = true;
                }
            });

        $connection->expects($this->once())
            ->method('prepare')
            ->willReturn($statementMock);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            true,
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => $productId,
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            static::createStub(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$deleteCommand],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => Uuid::fromBytesToHex($productId)],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        $event->success();

        static::assertTrue($this->requiredParameter[':entity_name']);
        static::assertTrue($this->requiredParameter[':entity_ids']);
    }

    public function testHandleDeletedEventStoresDataWillRollbackOnException(): void
    {
        $productId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('transactional')
            ->willThrowException(static::createStub(DeadlockException::class));

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            true,
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => $productId,
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            static::createStub(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$deleteCommand],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => Uuid::fromBytesToHex($productId)],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        // the exception from transactional is silently caught, so no exception is expected
        $event->success();
    }

    public function testHandleDeletedEventStoresDataMultipleEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $func) => $func());

        $statementMock = $this->createMock(Statement::class);
        // assert bindValue to be called 2 * 4 times (2 entities with 5 parameters)
        $statementMock->expects($this->exactly(8))
            ->method('bindValue')
            ->withAnyParameters();

        $connection->expects($this->once())
            ->method('prepare')
            ->willReturn($statementMock);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            true,
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => Uuid::randomBytes(),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            static::createStub(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $deleteCommand,
                $deleteCommand,
            ],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => [
                    'first-id' => 'product-id-1',
                    'second-id' => 'product-id-1',
                ],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        $event->success();
    }

    public function testHandleDeletedEventReturnsEarlyOnEmptyEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('transactional');

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            true,
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => [], // no ids given
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();
    }

    public function testHandleDeletedEventIgnoresEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('createQueryBuilder');

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock(),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            true,
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                IgnoredEntityDefinition::ENTITY_NAME => ['id' => '123'], // this entity is not registered
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();
    }

    public function testIfDeletionsAreNotStoredWhenConsentIsNotGiven(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('transactional');

        $consentService = $this->createConsentService(ConsentStatus::REVOKED);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $consentService,
            true,
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => '123'],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();

        static::assertSame(ConsentStatus::REVOKED, $consentService->getConsentState(BackendData::NAME, Context::createDefaultContext())->status);
    }

    public function testIfDeletionsAreNotStoredWhenCollectionIsDisabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('transactional');

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $this->createConsentService(ConsentStatus::ACCEPTED),
            false,
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => '123'],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();
    }

    private function createConsentService(ConsentStatus $status): ConsentService
    {
        $consentService = static::createStub(ConsentService::class);
        $consentService->method('getConsentState')
            ->willReturn(new ConsentState(
                BackendData::NAME,
                ConsentScope\System::NAME,
                ConsentScope\System::NAME,
                $status,
                'actor',
                '2023-09-01 12:00:00',
            ));

        return $consentService;
    }
}

/**
 * @internal
 */
class EntityWithSinglePrimaryKey extends EntityDefinition
{
    public const ENTITY_NAME = 'entity_with_single_primary_key';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new NonStorageAwareField('nonStorageAwarePrimaryKey'))->addFlags(new PrimaryKey()),
            new NonStorageAwareField('nonStorageAware'),
            new ReferenceVersionField(ProductDefinition::class, 'product_version_id'),
        ]);
    }
}

/**
 * @internal
 */
class IgnoredEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ignored_entity_definition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new NonStorageAwareField('name'),
            new ReferenceVersionField(ProductDefinition::class, 'product_version_id'),
        ]);
    }
}

/**
 * @internal
 */
class NonStorageAwareField extends Field
{
    protected function getSerializerClass(): string
    {
        /** @phpstan-ignore return.type (for test purpose) */
        return '';
    }
}

/**
 * @internal
 */
class DeletedEvent extends EntityDeleteEvent
{
    /**
     * @var array<array<string, string>>
     */
    private static array $ids = [];

    /**
     * @param array<WriteCommand> $commands
     * @param array<array<string, string>> $ids
     */
    public static function create(WriteContext $writeContext, array $commands, array $ids = []): EntityDeleteEvent
    {
        self::$ids = $ids;

        return parent::create($writeContext, $commands);
    }

    /**
     * @return array<array<string, string>|string>
     */
    public function getIds(string $entity): array
    {
        return \array_key_exists($entity, self::$ids) ? self::$ids[$entity] : [];
    }
}
