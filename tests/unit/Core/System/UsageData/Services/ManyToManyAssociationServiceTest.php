<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\UsageData\Services;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\UsageData\Services\ManyToManyAssociationService;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopwell\Core\Test\Stub\Doctrine\FakeResultFactory;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ManyToManyAssociationService::class)]
class ManyToManyAssociationServiceTest extends TestCase
{
    public function testGetMappingIdsForAssociationFields(): void
    {
        $ids = new IdsCollection();
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT `referenceColumn`, `localColumn` FROM `ManyToManyEntity` WHERE (`localColumn` IN (:ids)) AND (`product_version_id` = UNHEX(:versionId))',
                [
                    'ids' => [
                        Uuid::fromHexToBytes($ids->get('1')),
                        Uuid::fromHexToBytes($ids->get('2')),
                        Uuid::fromHexToBytes($ids->get('3')),
                    ],
                    'versionId' => Defaults::LIVE_VERSION,
                ]
            )
            ->willReturn(
                FakeResultFactory::createResult(
                    [
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('1')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-2')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('2')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                        [
                            'localColumn' => Uuid::fromHexToBytes($ids->get('3')),
                            'referenceColumn' => Uuid::fromHexToBytes($ids->get('referenceColumn-1')),
                        ],
                    ],
                    $connection,
                )
            );

        $service = new ManyToManyAssociationService($connection);

        $mappingDefinition = new MappingDefinition();
        $toManyDefinition = new ToManyDefinition();
        $registry = new StaticDefinitionInstanceRegistry(
            [$mappingDefinition, $toManyDefinition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $associationField = new ManyToManyAssociationField(
            'propertyName',
            ToManyDefinition::class,
            MappingDefinition::class,
            'localColumn',
            'referenceColumn',
        );
        $associationField->compile($registry);

        $result = $service->getMappingIdsForAssociationFields(
            [$associationField],
            [
                ['primaryKeyName' => $ids->get('1')],
                ['primaryKeyName' => $ids->get('2')],
                ['primaryKeyName' => $ids->get('3')],
            ],
            'primaryKeyName'
        );

        static::assertSame([
            'propertyName' => [
                Uuid::fromHexToBytes($ids->get('1')) => [
                    $ids->get('referenceColumn-1'),
                    $ids->get('referenceColumn-2'),
                ],
                Uuid::fromHexToBytes($ids->get('2')) => [
                    $ids->get('referenceColumn-1'),
                ],
                Uuid::fromHexToBytes($ids->get('3')) => [
                    $ids->get('referenceColumn-1'),
                ],
            ],
        ], $result);
    }
}

/**
 * @internal
 */
class MappingDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'ManyToManyEntity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new PrimaryKey()),
            new ManyToManyAssociationField('manyToMany', MockEntityDefinition::class, ManyToManyMappingEntityDefinition::class, 'manyToMany', 'manyToMany'),
        ]);
    }
}

/**
 * @internal
 */
class ToManyDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'to_many_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
        ]);
    }
}
