<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
class ChangeEmailRouteTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

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

    protected function tearDown(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->delete('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChangeInvalidPassword(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'foooware',
                    'email' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::CUSTOMER_PASSWORD_NOT_CORRECT', $response['errors'][0]['code']);
    }

    public function testChange(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopwell',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('test@fooware.de', $response['email']);
    }

    public function testChangeAndDeleteOldRecoveryEntities(): void
    {
        $recoveryData = [
            'customerId' => $this->customerId,
            'hash' => Random::getAlphanumericString(32),
        ];

        /** @var EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository */
        $customerRecoveryRepository = $this->getContainer()->get('customer_recovery.repository');
        $customerRecoveryRepository->create([$recoveryData], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopwell',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('customerId', $this->customerId));
        $ids = $customerRecoveryRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertSame('test@fooware.de', $response['email']);
        static::assertCount(0, $ids);
    }

    public function testChangeSuccessWithSameEmailOnDiffSalesChannel(): void
    {
        static::getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel', true);
        $this->customerRepository->update([
            [
                'id' => $this->customerId,
                'boundSalesChannelId' => $this->ids->get('sales-channel'),
            ],
        ], Context::createDefaultContext());

        $newEmail = 'test@fooware.de';

        $salesChannelContext = $this->createSalesChannelContext([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $this->createCustomerOfSalesChannel($salesChannelContext->getSalesChannelId(), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopwell',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('errors', $response);
        static::assertTrue($response['success']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($newEmail, $response['email']);
    }

    public function testChangeFailWithSameEmailOnSameSalesChannel(): void
    {
        $newEmail = 'test@fooware.de';

        $this->createCustomerOfSalesChannel($this->ids->get('sales-channel'), $newEmail);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopwell',
                    'email' => $newEmail,
                    'emailConfirmation' => $newEmail,
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertNotCount(0, $response['errors']);
        static::assertSame('VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotSame($newEmail, $response['email']);
    }

    public function testChangeSuccessWithNewsletterRecipient(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/account/customer',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->browser
            ->request(
                'POST',
                '/store-api/newsletter/subscribe',
                [
                    'email' => $response['email'],
                    'option' => 'direct',
                    'storefrontUrl' => 'http://localhost',
                ]
            );

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertSame(1, $count);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/change-email',
                [
                    'password' => 'shopwell',
                    'email' => 'test@fooware.de',
                    'emailConfirmation' => 'test@fooware.de',
                ]
            );

        $count = (int) static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT COUNT(*) FROM newsletter_recipient WHERE status = "direct" AND email = ?', [$response['email']]);
        static::assertSame(0, $count);

        $email = static::getContainer()->get(Connection::class)
            ->fetchOne('SELECT email FROM newsletter_recipient WHERE status = "direct" AND email = "test@fooware.de"');
        static::assertSame($email, 'test@fooware.de');
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,

            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => $password,
            'name' => 'Max Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => $customerId,
        ];

        $this->customerRepository->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function createCustomerOfSalesChannel(string $salesChannelId, string $email): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => $customerId,
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max Mustermann',
            'customerNumber' => $customerId,
            'email' => $email,
            'password' => 'shopwell',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => $salesChannelId,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $isCustomerBound = static::getContainer()->get(SystemConfigService::class)->get('core.systemWideLoginRegistration.isCustomerBoundToSalesChannel');
        $customer['boundSalesChannelId'] = $isCustomerBound ? $salesChannelId : null;

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
