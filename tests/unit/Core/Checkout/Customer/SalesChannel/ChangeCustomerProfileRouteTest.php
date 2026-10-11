<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\ChangeCustomerProfileRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopwell\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ChangeCustomerProfileRoute::class)]
class ChangeCustomerProfileRouteTest extends TestCase
{
    public function testVatIdsAreNormalizedBeforeValidation(): void
    {
        $countryId = Uuid::randomHex();
        $country = new CountryEntity();
        $country->setId($countryId);
        $country->setCheckVatIdPattern(true);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setCountryId($countryId);
        $billingAddress->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setDefaultBillingAddress($billingAddress);

        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn(new DataValidationDefinition());

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->once())
            ->method('validate')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data['vatIds']);

                    return true;
                }),
                static::isInstanceOf(DataValidationDefinition::class)
            );

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $data): bool {
                    static::assertSame(['DE123456789'], $data[0]['vatIds']);

                    return true;
                }),
                static::isInstanceOf(Context::class)
            );

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            $validator,
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'name' => 'Max Mustermann',
            'salutationId' => Uuid::randomHex(),
            'vatIds' => ['de 123456789'],
        ]);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testCustomFieldsGetPassed(): void
    {
        $customFields = new RequestDataBag(['test1' => '1', 'test2' => '2']);

        $customerRepository = static::createStub(EntityRepository::class);

        $storeApiCustomFieldMapper = $this->createMock(StoreApiCustomFieldMapper::class);
        $storeApiCustomFieldMapper
            ->expects($this->once())
            ->method('map')
            ->with('customer', $customFields)
            ->willReturn(['test1' => '1']);

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            $storeApiCustomFieldMapper,
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $data = new RequestDataBag([
            'customFields' => $customFields,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testAccountTypeGetPassed(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertArrayHasKey('accountType', $data[0]);

                return true;
            }));

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'salutationId' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testSalutationIdIsAssignedDefaultValue(): void
    {
        $salutationId = Uuid::randomHex();

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($salutationId) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['salutationId'], $salutationId);

                return true;
            }));

        $idSearchResult = new IdSearchResult(
            1,
            [$salutationId => ['data' => [], 'primaryKey' => $salutationId]],
            new Criteria(),
            Context::createDefaultContext(),
        );

        $salutationRepository = static::createStub(EntityRepository::class);
        $salutationRepository->method('searchIds')->willReturn($idSearchResult);

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
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

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-customer-profile-route.change.pre', static function (ChangeCustomerProfileRouteExtension $extension) use ($data, $context, $customer, $response): void {
            static::assertSame(['data' => $data, 'context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangeCustomerProfileRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($data, $context, $customer));
    }
}
