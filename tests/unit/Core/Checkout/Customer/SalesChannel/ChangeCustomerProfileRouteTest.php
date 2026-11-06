<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Extension\ChangeCustomerProfileRouteExtension;
use Shopwell\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopwell\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopwell\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\Framework\Validation\DataValidationDefinition;
use Shopwell\Core\Framework\Validation\DataValidator;
use Shopwell\Core\System\Country\CountryEntity;
use Shopwell\Core\System\Dictionary\Aggregate\DictionaryEntry\DictionaryEntryDefinition;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopwell\Core\System\SalesChannel\SuccessResponse;
use Shopwell\Core\Test\Generator;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
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
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'name' => 'Max Mustermann',
            'gender' => 'male',
            'vatIds' => ['de 123456789'],
        ]);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testVatIdsAreValidatedAgainstTheActiveBillingCountry(): void
    {
        $defaultCountry = new CountryEntity();
        $defaultCountry->setId(Uuid::randomHex());
        $defaultCountry->setVatIdRequired(false);

        $defaultBillingAddress = new CustomerAddressEntity();
        $defaultBillingAddress->setCountryId($defaultCountry->getId());
        $defaultBillingAddress->setCountry($defaultCountry);

        $activeCountry = new CountryEntity();
        $activeCountry->setId(Uuid::randomHex());
        $activeCountry->setVatIdRequired(true);

        $activeBillingAddress = new CustomerAddressEntity();
        $activeBillingAddress->setCountryId($activeCountry->getId());
        $activeBillingAddress->setCountry($activeCountry);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setDefaultBillingAddress($defaultBillingAddress);
        $customer->setActiveBillingAddress($activeBillingAddress);

        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory
            ->method('update')
            ->willReturn($validation);

        $change = new ChangeCustomerProfileRoute(
            static::createStub(EntityRepository::class),
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'company' => 'Test Company',
            'salutationId' => Uuid::randomHex(),
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);

        $vatIdConstraints = $validation->getProperty('vatIds');

        $vatIdentification = array_values(array_filter($vatIdConstraints, static fn (Constraint $constraint) => $constraint instanceof CustomerVatIdentification))[0] ?? null;
        static::assertInstanceOf(CustomerVatIdentification::class, $vatIdentification);
        static::assertSame($activeCountry->getId(), $vatIdentification->getCountryId());
        static::assertContainsEquals(new NotBlank(), $vatIdConstraints);
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
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $data = new RequestDataBag([
            'customFields' => $customFields,
            'gender' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testBusinessCustomerKeepsCompanyAndVatIdsWhenTheRequestOmitsThem(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessCustomer(), [
            'name' => 'Jane Doe',
        ]);

        static::assertSame('Jane Doe', $written['name']);
        static::assertArrayNotHasKey('accountType', $written);
        static::assertArrayNotHasKey('company', $written);
        static::assertArrayNotHasKey('vatIds', $written);
        static::assertSame([], $validation->getProperty('company'));
        static::assertSame([], $validation->getProperty('vatIds'));
    }

    public function testBusinessCustomerChangesCompanyAndVatIdsSentWithoutAccountType(): void
    {
        [$written, $validation] = $this->changeProfile($this->createBusinessCustomer(), [
            'name' => 'Jane Doe',
            'company' => 'New Company',
            'vatIds' => ['DE987654321'],
        ]);

        static::assertArrayNotHasKey('accountType', $written);
        static::assertSame('New Company', $written['company']);
        static::assertSame(['DE987654321'], $written['vatIds']);
        static::assertContainsEquals(new NotBlank(), $validation->getProperty('company'));
        static::assertContainsEquals(new NotBlank(), $validation->getProperty('vatIds'));
    }

    public function testPrivateCustomerWithoutAccountTypeGetsNoCompany(): void
    {
        $customer = $this->createBusinessCustomer();
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        [$written] = $this->changeProfile($customer, [
            'name' => 'Jane Doe',
            'company' => 'New Company',
        ]);

        static::assertSame('', $written['company']);
        static::assertNull($written['vatIds']);
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
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'gender' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testGenderIsAssignedDefaultValue(): void
    {
        $gender = DictionaryEntryDefinition::NOT_SPECIFIED;

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use ($gender) {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame($data[0]['gender'], $gender);

                return true;
            }));

        $change = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $customer = new CustomerEntity();
        $customer->setId('customer1');

        $data = new RequestDataBag([
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'gender' => '',
        ]);

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $change->change($data, $salesChannelContext, $customer);
    }

    public function testPhoneNumberIsPersistedTrimmed(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertIsArray($data[0]);
                static::assertArrayHasKey('phoneNumber', $data[0]);
                static::assertSame('0123456789', $data[0]['phoneNumber']);

                return true;
            }));

        $change = $this->createRoute($customerRepository);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        $data = new RequestDataBag([
            'gender' => '1',
            'phoneNumber' => " 0123456789\u{3000}",
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testBlankPhoneNumberUnbindsIt(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertIsArray($data[0]);
                static::assertArrayHasKey('phoneNumber', $data[0]);
                static::assertNull($data[0]['phoneNumber']);

                return true;
            }));

        $change = $this->createRoute($customerRepository);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        $data = new RequestDataBag([
            'gender' => '1',
            'phoneNumber' => "\u{3000} ",
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
    }

    public function testPhoneNumberIsLeftUntouchedWhenItIsNotSubmitted(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) {
                static::assertIsArray($data[0]);
                static::assertArrayNotHasKey('phoneNumber', $data[0]);

                return true;
            }));

        $change = $this->createRoute($customerRepository);

        $customer = new CustomerEntity();
        $customer->setId('customer1');
        $customer->setPhoneNumber('0123456789');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);

        $data = new RequestDataBag([
            'gender' => '1',
        ]);

        $change->change($data, static::createStub(SalesChannelContext::class), $customer);
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
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($data, $context, $customer));
    }

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    private function createRoute(EntityRepository $customerRepository): ChangeCustomerProfileRoute
    {
        return new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            static::createStub(CustomerValidationFactory::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{0: array<string, mixed>, 1: DataValidationDefinition}
     */
    private function changeProfile(CustomerEntity $customer, array $request): array
    {
        $validation = new DataValidationDefinition();
        $validationFactory = static::createStub(CustomerValidationFactory::class);
        $validationFactory->method('update')->willReturn($validation);

        $written = [];
        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data) use (&$written): bool {
                $written = $data[0];

                return true;
            }));

        $route = new ChangeCustomerProfileRoute(
            $customerRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $validationFactory,
            static::createStub(StoreApiCustomFieldMapper::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $route->change(new RequestDataBag([...$request]), static::createStub(SalesChannelContext::class), $customer);

        return [$written, $validation];
    }

    private function createBusinessCustomer(): CustomerEntity
    {
        $country = new CountryEntity();
        $country->setId(Uuid::randomHex());
        $country->setVatIdRequired(true);

        $billingAddress = new CustomerAddressEntity();
        $billingAddress->setCountryId($country->getId());
        $billingAddress->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setCompany('Example Inc.');
        $customer->setVatIds(['DE123456789']);
        $customer->setActiveBillingAddress($billingAddress);

        return $customer;
    }
}
