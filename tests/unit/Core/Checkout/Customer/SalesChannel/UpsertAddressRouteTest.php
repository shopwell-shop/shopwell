<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\UpsertAddressRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\UpsertAddressRouteResponse;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Event\NestedEventCollection;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(UpsertAddressRoute::class)]
class UpsertAddressRouteTest extends TestCase
{
    public function testCustomFields(): void
    {
        $systemConfigService = static::createStub(SystemConfigService::class);
        $systemConfigService
            ->method('get')
            ->willReturn('1');

        $result = static::createStub(EntitySearchResult::class);
        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $result->method('getEntities')->willReturn(new CustomerAddressCollection([$address]));

        $salesChannelAddressRepository = static::createStub(SalesChannelRepository::class);
        $salesChannelAddressRepository->method('search')->willReturn($result);

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) {
                static::assertSame(['mapped' => 1], $data[0]['customFields']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            CustomerAddressDefinition::ENTITY_NAME => [
                ['name' => 'mapped', 'type' => 'int'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            $systemConfigService,
            $customFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
            'customFields' => [
                'test' => '1',
                'mapped' => '1',
            ],
        ]);

        $upsert->upsert(null, $data, $salesChannelContext, $customer);
    }

    public function testAddressStringFieldsAreTrimmedBeforeUpsert(): void
    {
        $countryId = Uuid::randomHex();
        $salutationId = Uuid::randomHex();
        $customerId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(static function (array $data) use ($countryId, $salutationId, $customerId) {
                static::assertCount(1, $data);
                static::assertSame($salutationId, $data[0]['salutationId']);
                static::assertSame('Max', $data[0]['firstName']);
                static::assertSame('Mustermann', $data[0]['lastName']);
                static::assertSame('Main Street 1', $data[0]['street']);
                static::assertSame('12345', $data[0]['zipcode']);
                static::assertSame('Berlin', $data[0]['city']);
                static::assertSame('Shopwell', $data[0]['company']);
                static::assertSame('Core', $data[0]['department']);
                static::assertSame('Dr.', $data[0]['title']);
                static::assertSame('123456', $data[0]['phoneNumber']);
                static::assertSame('Line 1', $data[0]['additionalAddressLine1']);
                static::assertSame('Line 2', $data[0]['additionalAddressLine2']);
                static::assertSame($countryId, $data[0]['countryId']);
                static::assertNull($data[0]['countryStateId']);
                static::assertSame(['note' => '  keep custom field whitespace  '], $data[0]['customFields']);
                static::assertSame($customerId, $data[0]['customerId']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([]), []);
            });

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());

        $salesChannelAddressRepository = static::createStub(SalesChannelRepository::class);
        $salesChannelAddressRepository->method('search')->willReturn(
            new EntitySearchResult(
                CustomerAddressDefinition::ENTITY_NAME,
                1,
                new CustomerAddressCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $addressValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $addressValidationFactory
            ->method('create')
            ->willReturn(new DataValidationDefinition('address.create'));

        $customFieldMapper = new StoreApiCustomFieldMapper(static::createStub(Connection::class), [
            CustomerAddressDefinition::ENTITY_NAME => [
                ['name' => 'note', 'type' => 'text'],
            ],
        ]);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            static::createStub(DataValidator::class),
            new EventDispatcher(),
            $addressValidationFactory,
            static::createStub(SystemConfigService::class),
            $customFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'salutationId' => $salutationId,
            'firstName' => "\nMax\t",
            'lastName' => "\rMustermann ",
            'street' => "\t Main Street 1 \n",
            'zipcode' => "    12345\t",
            'city' => "\rBerlin\n",
            'countryId' => $countryId,
            'countryStateId' => '',
            'company' => "\tShopwell ",
            'department' => "\nCore    ",
            'title' => "\tDr.\n",
            'phoneNumber' => "\t123456\n",
            'additionalAddressLine1' => '        Line 1         ',
            'additionalAddressLine2' => "    Line 2\r",
            'customFields' => [
                'note' => '  keep custom field whitespace  ',
            ],
        ]);

        $upsert->upsert(null, $data, Generator::generateSalesChannelContext(), $customer);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $addressRepository = $this->createMock(EntityRepository::class);
        $addressRepository
            ->expects($this->once())
            ->method('upsert')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $address = new CustomerAddressEntity();
        $address->setId(Uuid::randomHex());
        $address->setSalutationId($salutationId);

        $salesChannelAddressRepository = $this->createMock(SalesChannelRepository::class);
        $salesChannelAddressRepository->expects($this->once())->method('search')->willReturn(
            new EntitySearchResult(
                'customer_address',
                1,
                new EntityCollection([$address]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = static::createStub(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $systemConfigService = static::createStub(SystemConfigService::class);

        $upsert = new UpsertAddressRoute(
            $addressRepository,
            $salesChannelAddressRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            $systemConfigService,
            static::createStub(StoreApiCustomFieldMapper::class),
            $salutationRepository,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '',
        ]);

        $upsert->upsert(null, $data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testPublishesExtension(): void
    {
        $addressId = Uuid::randomHex();
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new UpsertAddressRouteResponse(new CustomerAddressEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('upsert-address-route.upsert.pre', static function (UpsertAddressRouteExtension $extension) use ($addressId, $data, $context, $customer, $response): void {
            static::assertSame(['addressId' => $addressId, 'data' => $data, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new UpsertAddressRoute(
            static::createStub(EntityRepository::class),
            static::createStub(SalesChannelRepository::class),
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->upsert($addressId, $data, $context, $customer));
    }
}
