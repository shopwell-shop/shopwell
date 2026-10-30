<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class ChangeCustomerProfileRouteTest extends TestCase
{
    use CustomerTestTrait;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private string $customerId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = static::getContainer()->get('customer.repository');

        $email = Uuid::randomHex() . '@example.com';
        $this->customerId = $this->createCustomer('shopwell', $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopwell',
                ]
            );

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);

        $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
        static::assertContains('/name', $sources);
    }

    public function testChangeName(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'gender' => $this->getValidGender(),
                    'name' => 'Max Mustermann',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $this->browser->request('GET', '/store-api/account/customer');
        $customer = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('Max Mustermann', $customer['name']);
        static::assertSame($this->getValidGender(), $customer['gender']);
    }

    public function testChangeProfileDataWithCommercialAccount(): void
    {
        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        static::assertSame(['DE123456789'], $customer->getVatIds());
        static::assertSame($changeData['company'], $customer->getCompany());
        static::assertSame($changeData['name'], $customer->getName());
    }

    public function testChangeProfileDataWithCommercialAccountNormalizesVatIdBeforeMatchingRegex(): void
    {
        $this->setVatIdOfTheCountryToValidateFormat();

        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'de 123456789',
            ],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        static::assertSame(['DE123456789'], $customer->getVatIds());
    }

    public function testChangeProfileDataWithCommercialAccountAndVatIdsIsEmpty(): void
    {
        $this->setVatIdOfTheCountryToValidateFormat();

        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        static::assertNull($customer->getVatIds());
        static::assertSame($changeData['company'], $customer->getCompany());
        static::assertSame($changeData['name'], $customer->getName());
    }

    /**
     * @param list<string> $vatIds
     */
    #[TestWith([[]])]
    #[TestWith([['']])]
    public function testChangeProfileDataClearVatIds(array $vatIds): void
    {
        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['success']);

        $customer = $this->getCustomer();
        static::assertSame(['DE123456789'], $customer->getVatIds());

        $changeData['vatIds'] = $vatIds;
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['success']);

        $customer = $this->getCustomer();
        static::assertNull($customer->getVatIds());
    }

    public function testChangeProfileWithoutGender(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'name' => 'Max Mustermann',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);
    }

    public function testChangeProfileToNotSpecifiedWithoutSubmittedGender(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'name' => 'Max Mustermann',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public static function dataProviderVatIds(): \Generator
    {
        yield 'Error when vatIds is require but no validate format, and has value is empty' => [
            [''],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds is require but no validate format, and without vatIds in parameters' => [
            null,
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds is require but no validate format, and has value is null and empty value' => [
            [null, ''],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            false,
            null,
        ];

        yield 'Success when vatIds is require but no validate format, and has one of the value is not null' => [
            [null, 'some-text'],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            true,
            ['SOME-TEXT'],
        ];

        yield 'Success when vatIds is require but no validate format, and has value is random string' => [
            ['some-text'],
            [
                'required' => true,
                'validateFormat' => false,
            ],
            true,
            ['SOME-TEXT'],
        ];

        yield 'Success when vatIds need to validate format but no require and has value is empty' => [
            [],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is null' => [
            [null],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is blank' => [
            [''],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            null,
        ];

        yield 'Error when vatIds need to validate format but no require and has value is boolean' => [
            [true],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            false,
            null,
        ];

        yield 'Error when vatIds need to validate format but no require and has value is incorrect format' => [
            ['random-string'],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            false,
            null,
        ];

        yield 'Success when vatIds need to validate format but no require and has value is correct format' => [
            ['123456789'],
            [
                'required' => false,
                'validateFormat' => true,
            ],
            true,
            ['123456789'],
        ];
    }

    /**
     * @param list<string|true|null>|null $vatIds
     * @param array<string, bool> $constraint
     * @param array<string>|null $expectedVatIds
     */
    #[DataProvider('dataProviderVatIds')]
    public function testChangeVatIdsOfCommercialAccount(?array $vatIds, array $constraint, bool $shouldBeValid, ?array $expectedVatIds): void
    {
        if (($constraint['required'] ?? false) === true) {
            $this->setVatIdOfTheCountryToBeRequired();
        }

        if (($constraint['validateFormat'] ?? false) === true) {
            $this->setVatIdOfTheCountryToValidateFormat();
        }

        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
        ];
        if ($vatIds !== null) {
            $changeData['vatIds'] = $vatIds;
        }

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (!$shouldBeValid) {
            static::assertArrayHasKey('errors', $response);

            $sources = array_column(array_column($response['errors'], 'source'), 'pointer');
            static::assertContains('/vatIds', $sources);

            return;
        }

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        if ($expectedVatIds === null) {
            static::assertNull($customer->getVatIds());
        } else {
            static::assertSame($expectedVatIds, $customer->getVatIds());
        }

        static::assertSame($changeData['company'], $customer->getCompany());
        static::assertSame($changeData['name'], $customer->getName());
    }

    public function testVatIdIsRequiredWhenTheActiveBillingCountryRequiresIt(): void
    {
        $addressId = $this->createAddressInNewCountry(['vatIdRequired' => true]);
        $this->browser->request('PATCH', '/store-api/context', ['billingAddressId' => $addressId]);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'gender' => $this->getValidGender(),
                    'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
                    'name' => 'Max Mustermann',
                    'company' => 'Test Company',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertContains('/vatIds', array_column(array_column($response['errors'], 'source'), 'pointer'));
    }

    public function testVatIdIsCheckedAgainstThePatternOfTheActiveBillingCountry(): void
    {
        $this->setVatIdOfTheCountryToValidateFormat();
        $addressId = $this->createAddressInNewCountry(['checkVatIdPattern' => true, 'vatIdPattern' => 'ATU[0-9]{8}']);
        $this->browser->request('PATCH', '/store-api/context', ['billingAddressId' => $addressId]);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => ['DE123456789'],
        ];

        $this->browser->request('POST', '/store-api/account/change-profile', $changeData);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertContains('/vatIds', array_column(array_column($response['errors'], 'source'), 'pointer'));

        $changeData['vatIds'] = ['ATU12345678'];

        $this->browser->request('POST', '/store-api/account/change-profile', $changeData);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['success']);
        static::assertSame(['ATU12345678'], $this->getCustomer()->getVatIds());
    }

    public function testChangeProfileDataWithPrivateAccount(): void
    {
        $changeData = [
            'gender' => $this->getValidGender(),
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'name' => 'FirstName LastName',
        ];
        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        static::assertNull($customer->getVatIds());
        static::assertNull($customer->getCompany());
        static::assertSame($changeData['name'], $customer->getName());
    }

    public function testChangeSuccessWithNewsletterRecipient(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => $response['email'],
                    'name' => $response['name'],
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $newsletterRecipient = static::getContainer()->get(Connection::class)
            ->fetchAssociative('SELECT * FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertIsArray($newsletterRecipient);

        static::assertSame($newsletterRecipient['name'], $response['name']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                [
                    'gender' => $this->getValidGender(),
                    'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
                    'name' => 'FirstName LastName',
                ]
            );

        $newsletterRecipient = static::getContainer()->get(Connection::class)
            ->fetchAssociative('SELECT * FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertIsArray($newsletterRecipient);

        static::assertSame($newsletterRecipient['name'], 'FirstName LastName');
    }

    public function testChangeWithAllowedAccountType(): void
    {
        $accountTypes = static::getContainer()->getParameter('customer.account_types');
        static::assertIsArray($accountTypes);
        static::assertNotCount(0, $accountTypes);
        $accountType = $accountTypes[array_rand($accountTypes)];

        $changeData = [
            'accountType' => $accountType,
            'gender' => $this->getValidGender(),
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();

        static::assertSame($accountType, $customer->getAccountType());
    }

    public function testProfileCanBeChangedWithEmptyAccountType(): void
    {
        $updateGender = 'female';

        $changeData = [
            'accountType' => '',
            'gender' => $updateGender,
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customer = $this->getCustomer();
        static::assertSame($updateGender, $customer->getGender());
    }

    public function testBusinessCustomerKeepsCompanyAndVatIdsWhenAccountTypeIsOmitted(): void
    {
        $this->customerRepository->update([
            ['id' => $this->customerId, 'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'company' => 'Example Inc.', 'vatIds' => ['DE123456789']],
        ], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/account/change-profile', [
            'gender' => $this->getValidGender(),
            'name' => 'Jane Doe',
        ]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['success']);

        $customer = $this->getCustomer();
        static::assertSame('Jane Doe', $customer->getName());
        static::assertSame(CustomerEntity::ACCOUNT_TYPE_BUSINESS, $customer->getAccountType());
        static::assertSame('Example Inc.', $customer->getCompany());
        static::assertSame(['DE123456789'], $customer->getVatIds());
    }

    public function testCompanySentWithoutAccountTypeIsValidatedForBusinessCustomers(): void
    {
        $this->customerRepository->update([
            ['id' => $this->customerId, 'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'company' => 'Example Inc.'],
        ], Context::createDefaultContext());

        $this->browser->request('POST', '/store-api/account/change-profile', [
            'gender' => $this->getValidGender(),
            'name' => 'Jane Doe',
            'company' => '',
        ]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertContains('/company', array_column(array_column($response['errors'], 'source'), 'pointer'));
        static::assertSame('Example Inc.', $this->getCustomer()->getCompany());
    }

    public function testChangeWithWrongAccountType(): void
    {
        $accountTypes = static::getContainer()->getParameter('customer.account_types');
        static::assertIsArray($accountTypes);
        $notAllowedAccountType = implode('', $accountTypes);
        $changeData = [
            'accountType' => $notAllowedAccountType,
            'gender' => $this->getValidGender(),
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertIsArray($response['errors'][0]);
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testChangeWithoutAccountTypeFallbackToDefaultValue(): void
    {
        $changeData = [
            'gender' => $this->getValidGender(),
            'name' => 'Max Mustermann',
            'company' => 'Test Company',
            'vatIds' => [
                'DE123456789',
            ],
        ];

        $this->browser->request(
            'POST',
            '/store-api/account/change-profile',
            $changeData
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->customerId));

        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($customer);

        $customerDefinition = new CustomerDefinition();
        static::assertArrayHasKey('accountType', $customerDefinition->getDefaults());
        static::assertSame($customerDefinition->getDefaults()['accountType'], $customer->getAccountType());
    }

    public function testChangeProfileWithCustomFields(): void
    {
        $context = Context::createDefaultContext();

        $this->customerRepository->update([
            [
                'id' => $this->customerId,
                'customFields' => [
                    'initialCustomField' => 'initialValueShouldStay',
                ],
            ],
        ], $context);

        $changeData = [
            'gender' => $this->getValidGender(),
            'name' => 'Max Mustermann',
            'customFields' => [
                'randomCustomField' => 'randomValue',
            ],
        ];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-profile',
                $changeData
            );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);
        $response = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        static::assertTrue($response['success']);

        $customFields = $this->getCustomer()->getCustomFields();
        static::assertIsArray($customFields);
        static::assertSame('initialValueShouldStay', $customFields['initialCustomField']);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('payment'),
                'name' => $this->ids->get('payment'),
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            [
                'id' => $this->ids->create('payment2'),
                'name' => $this->ids->get('payment2'),
                'technicalName' => 'payment_test2',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
                'availabilityRule' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
        ];

        static::getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());
    }

    private function createCustomer(?string $password = null, ?string $email = null, ?bool $guest = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        if ($email === null) {
            $email = Uuid::randomHex() . '@example.com';
        }

        if ($password === null) {
            $password = Uuid::randomHex();
        }

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'gender' => $this->getValidGender(),
                'countryId' => $this->getValidCountryId($this->ids->create('sales-channel')),
            ],
            'defaultBillingAddressId' => $addressId,

            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => $password,
            'name' => 'Max Mustermann',
            'guest' => $guest,
            'gender' => $this->getValidGender(),
            'customerNumber' => '12345',
        ];

        $this->customerRepository->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @param array<string, mixed> $country
     */
    private function createAddressInNewCountry(array $country): string
    {
        $addressId = Uuid::randomHex();

        static::getContainer()->get('customer_address.repository')->create([[
            'id' => $addressId,
            'customerId' => $this->customerId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'street' => 'Teststraße 1',
            'city' => 'Wien',
            'zipcode' => '1010',
            'salutationId' => $this->getValidSalutationId(),
            'country' => ['name' => 'VAT Test Country', ...$country],
        ]], Context::createDefaultContext());

        return $addressId;
    }

    private function setVatIdOfTheCountryToValidateFormat(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement(
                'UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}"
                 WHERE id = :id',
                [
                    'id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->create('sales-channel'))),
                ]
            );
    }

    private function setVatIdOfTheCountryToBeRequired(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement(
                'UPDATE `country` SET `vat_id_required` = 1
                 WHERE id = :id',
                [
                    'id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->create('sales-channel'))),
                ]
            );
    }

    private function getCustomer(): CustomerEntity
    {
        $criteria = new Criteria([$this->customerId]);

        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($customer);

        return $customer;
    }
}
