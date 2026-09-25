<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopwell\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopwell\Core\Checkout\Order\OrderStates;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
#[Package('framework')]
class RemoteAddressFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRemoteAddressSerializerAnonymize(): void
    {
        $this->setConfig();

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderCustomer = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderCustomerEntity::class, $orderCustomer);
        static::assertNotSame($remoteAddress, $orderCustomer->getRemoteAddress());
        static::assertSame(IpUtils::anonymize($remoteAddress), $orderCustomer->getRemoteAddress());
    }

    public function testRemoteAddressSerializerNoAnonymize(): void
    {
        $this->setConfig(true);

        $remoteAddress = '127.0.0.1';
        $orderId = $this->createOrderWithRemoteAddress($remoteAddress);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderCustomer = static::getContainer()->get('order_customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderCustomerEntity::class, $orderCustomer);
        static::assertSame($remoteAddress, $orderCustomer->getRemoteAddress());
    }

    public function testSetRemoteAddressByLogin(): void
    {
        $this->setConfig();

        $customerId = $this->createCustomer();

        static::getContainer()->get(AccountService::class)
            ->loginByCredentials('test@example.com', 'shopware', $this->createSalesChannelContext());

        $criteria = new Criteria([$customerId]);

        $customer = static::getContainer()->get('customer.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertNotSame('127.0.0.1', $customer->getRemoteAddress());
        static::assertSame(IpUtils::anonymize('127.0.0.1'), $customer->getRemoteAddress());
    }

    private function setConfig(bool $value = false): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.loginRegistration.customerIpAddressesNotAnonymously', $value);
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    private function createOrderWithRemoteAddress(string $remoteAddress): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);

        $customerId = $this->createCustomer();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'remoteAddress' => $remoteAddress,
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'remoteAddress' => '127.0.0.1',
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
