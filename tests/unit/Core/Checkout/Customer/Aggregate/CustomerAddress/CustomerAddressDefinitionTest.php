<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerAddress;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerAddressDefinition::class)]
class CustomerAddressDefinitionTest extends TestCase
{
    private CustomerAddressDefinition $definition;

    protected function setUp(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [CustomerAddressDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(CustomerAddressDefinition::ENTITY_NAME);
        static::assertInstanceOf(CustomerAddressDefinition::class, $definition);
        $this->definition = $definition;
    }

    public function testEntityName(): void
    {
        static::assertSame('customer_address', $this->definition->getEntityName());
    }

    public function testEntityClass(): void
    {
        static::assertSame(CustomerAddressEntity::class, $this->definition->getEntityClass());
    }

    public function testCollectionClass(): void
    {
        static::assertSame(CustomerAddressCollection::class, $this->definition->getCollectionClass());
    }

    public function testSince(): void
    {
        static::assertSame('6.0.0.0', $this->definition->since());
    }

    public function testIdFieldIsPrimaryKey(): void
    {
        $field = $this->definition->getFields()->get('id');
        static::assertInstanceOf(IdField::class, $field);
        static::assertTrue($field->is(PrimaryKey::class));
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerIdField(): void
    {
        $field = $this->definition->getFields()->get('customerId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testCountryIdField(): void
    {
        $field = $this->definition->getFields()->get('countryId');
        static::assertInstanceOf(FkField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testNameField(): void
    {
        $field = $this->definition->getFields()->get('name');
        static::assertInstanceOf(StringField::class, $field);
        static::assertTrue($field->is(Required::class));
    }

    public function testCustomerAssociation(): void
    {
        $field = $this->definition->getFields()->get('customer');
        static::assertInstanceOf(ManyToOneAssociationField::class, $field);
    }
}
