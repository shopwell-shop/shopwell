<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalesChannelCustomerAddressDefinition::class)]
class SalesChannelCustomerAddressDefinitionTest extends TestCase
{
    public function testEntityAndCollectionClasses(): void
    {
        $definition = new SalesChannelCustomerAddressDefinition();

        static::assertSame(SalesChannelCustomerAddressEntity::class, $definition->getEntityClass());
        static::assertSame(SalesChannelCustomerAddressCollection::class, $definition->getCollectionClass());
    }

    public function testProcessCriteriaWithoutCustomerFiltersOnNull(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext(overrides: ['customer' => null]);

        (new SalesChannelCustomerAddressDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('customerId', null)], $criteria->getFilters());
    }

    public function testProcessCriteria(): void
    {
        $definition = new SalesChannelCustomerAddressDefinition();
        $criteria = new Criteria();
        $context = Generator::generateSalesChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertNotCount(0, $criteria->getFilters());

        $filter = $criteria->getFilters()[0] ?? null;
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('customerId', $filter->getField());
        static::assertSame($context->getCustomer()?->getId(), $filter->getValue());
    }

    public function testDefineFields(): void
    {
        $definition = new SalesChannelCustomerAddressDefinition();

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition->compile($registry);
        $fields = $definition->getFields();

        $billingField = $fields->get('isDefaultBillingAddress');
        static::assertInstanceOf(BoolField::class, $billingField);
        static::assertTrue($billingField->is(Runtime::class));
        static::assertTrue($billingField->is(ApiAware::class));

        $shippingField = $fields->get('isDefaultShippingAddress');
        static::assertInstanceOf(BoolField::class, $shippingField);
        static::assertTrue($shippingField->is(Runtime::class));
        static::assertTrue($shippingField->is(ApiAware::class));
    }
}
