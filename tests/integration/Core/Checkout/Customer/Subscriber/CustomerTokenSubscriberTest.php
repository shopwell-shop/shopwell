<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerTokenSubscriberTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customerRepository = static::getContainer()->get('customer.repository');
    }

    public function testCustomerTokenSubscriber(): void
    {
        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertSame(
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            \json_decode((string) $this->connection->fetchOne('SELECT payload FROM sales_channel_api_context WHERE token = "test"'), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCustomerTokenSubscriberStorefrontShouldStillBeLoggedIn(): void
    {
        $customerId = $this->createCustomer();

        $request = Request::create('/');
        // a storefront request owns the session
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST, true);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getToken')->willReturn('test');
        $context->method('getCustomerId')->willReturn($customerId);
        $context->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        static::getContainer()->get('request_stack')->push($request);

        $newToken = null;

        $context->method('assign')->willReturnCallback(static function ($array) use ($context, &$newToken) {
            $newToken = $array['token'];

            return $context;
        });

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertNotNull($newToken);

        static::assertSame(
            [
                'customerId' => '1234',
            ],
            \json_decode((string) $this->connection->fetchOne('SELECT payload FROM sales_channel_api_context WHERE token = ?', [$newToken]), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testDeleteCustomer(): void
    {
        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->delete([
            [
                'id' => $customerId,
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM sales_channel_api_context WHERE token = ?', ['test']));
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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

        $this->customerRepository->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
