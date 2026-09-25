<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManyToOneAssociationFieldSerializer::class)]
class ManyToOneAssociationFieldSerializerTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $payload
     */
    #[DataProvider('invalidArrayProvider')]
    public function testExceptionIsThrownIfDataIsNotAssociativeArray(array $payload): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::expectedAssociativeArray('/customer'));

        $result = $serializer->encode(
            $field,
            static::createStub(EntityExistence::class),
            new KeyValuePair('customer', $payload, true),
            $params
        );

        iterator_to_array($result);
    }

    public function testExceptionInNormalizationIsThrownIfDataIsNotArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::expectedArray('/0/customer'));

        $serializer->normalize(
            $field,
            ['customer' => 'foobar'],
            $params,
        );
    }

    public static function invalidArrayProvider(): \Generator
    {
        yield [
            'payload' => ['should-be-an-associative-array'],
        ];

        yield [
            'payload' => [1 => 'apple', 'orange'],
        ];

        yield [
            'payload' => [0 => 'apple', 1 => 'orange'],
        ];

        yield [
            'payload' => [3 => 'apple', 5 => 'orange'],
        ];
    }

    public function testCanEncodeAssociativeArray(): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                OrderDefinition::class => $orderDefinition = new OrderDefinition(),
                CustomerDefinition::class => new CustomerDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $field = $orderDefinition->getField('customer');

        static::assertInstanceOf(ManyToOneAssociationField::class, $field);

        $serializer = new ManyToOneAssociationFieldSerializer(static::createStub(WriteCommandExtractor::class));

        $params = new WriteParameterBag(
            $orderDefinition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/customer',
            new WriteCommandQueue()
        );

        $id = Uuid::randomHex();

        $result = $serializer->encode(
            $field,
            static::createStub(EntityExistence::class),
            new KeyValuePair('customer', ['id' => $id, 'name' => 'Jimmy'], true),
            $params
        );

        static::assertSame([], iterator_to_array($result));
    }
}

/**
 * @internal
 */
class OrderDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'order';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            new FkField('customer_id', 'customerId', CustomerDefinition::class),

            new ManyToOneAssociationField(
                'customer',
                'customer_id',
                CustomerDefinition::class,
                'id',
            ),
        ]);
    }
}

/**
 * @internal
 */
class CustomerDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'customer';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('first_name', 'first_name'))->addFlags(new Required()),
            (new StringField('last_name', 'last_name'))->addFlags(new Required()),

            new OneToManyAssociationField(
                'orders',
                OrderDefinition::class,
                'customer_id',
            ),
        ]);
    }
}
