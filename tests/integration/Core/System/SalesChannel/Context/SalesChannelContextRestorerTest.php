<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\CartRuleLoader;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Order\OrderConverter;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopwell\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopwell\Core\System\SalesChannel\Event\SalesChannelContextRestorerOrderCriteriaEvent;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelContextRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SalesChannelContextRestorer $contextRestorer;

    /**
     * @var array<string, Event>
     */
    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        /** @var AbstractSalesChannelContextFactory $contextFactory */
        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);

        $this->contextRestorer = new SalesChannelContextRestorer(
            $contextFactory,
            $cartRuleLoader,
            static::getContainer()->get(OrderConverter::class),
            static::getContainer()->get('order.repository'),
            $this->connection,
            $this->eventDispatcher
        );
    }

    public function testRestoreByOrder(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        static::getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByOrder($ids->create('order'), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByOrderUsesTheCustomerAddressMatchingTheOrderShippingAddress(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $ids = new IdsCollection();
        $shippingAddress = [
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max Mustermann',
            'street' => 'Ebbinghoff 10',
            'zipcode' => '48624',
            'city' => 'Schöppingen',
            'countryId' => $this->getValidCountryId(),
        ];
        $customer = $this->createCustomer([['id' => $ids->get('customer-shipping-address'), ...$shippingAddress]]);
        $this->createOrder($ids, $customer->getId(), ['id' => $ids->create('shipping-address'), ...$shippingAddress]);

        $salesChannelContext = $this->contextRestorer->restoreByOrder($ids->get('order'), Context::createDefaultContext());

        static::assertSame($ids->get('customer-shipping-address'), $salesChannelContext->getCustomer()?->getActiveShippingAddress()?->getId());
    }

    public function testRestoreByCustomer(): void
    {
        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);
        $ruleId = Uuid::randomHex();
        $rule = [
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new AlwaysValidRule())->getName()],
            ],
        ];

        // Create rule after create order
        static::getContainer()->get('rule.repository')
            ->create([$rule], $context);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue(\in_array($ruleId, $saleChanelContext->getRuleIds(), true));
    }

    public function testRestoreByCustomerPassesStates(): void
    {
        $context = Context::createDefaultContext();
        $context->addState('foo');

        $ids = new IdsCollection();
        $this->createOrder($ids);

        $saleChanelContext = $this->contextRestorer->restoreByCustomer($this->createCustomer()->getId(), $context);
        static::assertTrue($saleChanelContext->getContext()->hasState('foo'));
    }

    public function testOrderCriteriaEventIsFired(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();
        $this->createOrder($ids);

        $this->eventDispatcher->addListener(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->callbackFn);
        $this->contextRestorer->restoreByOrder($ids->create('order'), $context);

        static::assertArrayHasKey(SalesChannelContextRestorerOrderCriteriaEvent::class, $this->events);
        $salesChannelContextRestorerCriteriaEvent = $this->events[SalesChannelContextRestorerOrderCriteriaEvent::class];
        static::assertInstanceOf(SalesChannelContextRestorerOrderCriteriaEvent::class, $salesChannelContextRestorerCriteriaEvent);
    }

    /**
     * @param array<string, string>|null $shippingAddress
     */
    private function createOrder(IdsCollection $ids, ?string $customerId = null, ?array $shippingAddress = null): void
    {
        $orderCustomer = [
            'id' => $ids->get('customer'),
            'salutationId' => $this->getValidSalutationId(),
            'email' => 'test',
            'name' => 'test test',
        ];

        if ($customerId !== null) {
            $orderCustomer['customerId'] = $customerId;
        } else {
            $orderCustomer['customer'] = (new CustomerBuilder($ids, '10000'))
                ->add('guest', true)
                ->add('createdAt', new \DateTime('- 25 hours'))->build();
        }

        $data = [
            'id' => $ids->create('order'),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'billingAddressId' => $ids->create('billing-address'),
            'currencyId' => Defaults::CURRENCY,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'orderDateTime' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyFactor' => 1,
            'stateId' => $this->getStateId('open', 'order.state'),
            'price' => new CartPrice(200, 200, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'ruleIds' => [$ids->get('rule')],
            'orderCustomer' => $orderCustomer,
            'addresses' => [
                [
                    'id' => $ids->create('billing-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'asd asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
                $shippingAddress ?? [
                    'id' => $ids->create('shipping-address'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'asd asd',
                    'street' => 'asd',
                    'zipcode' => 'asd',
                    'city' => 'asd',
                ],
            ],
            'lineItems' => [
                [
                    'id' => $ids->create('line-item'),
                    'identifier' => $ids->create('line-item'),
                    'quantity' => 1,
                    'label' => 'label',
                    'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                    'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                ],
            ],
            'deliveries' => [
                [
                    'id' => $ids->create('delivery'),
                    'shippingOrderAddressId' => $ids->create('shipping-address'),
                    'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
                    'stateId' => $this->getStateId('open', 'order_delivery.state'),
                    'trackingCodes' => [],
                    'shippingDateEarliest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'positions' => [
                        [
                            'id' => $ids->create('position'),
                            'orderLineItemId' => $ids->create('line-item'),
                            'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        ],
                    ],
                ],
            ],
            'transactions' => [
                [
                    'id' => $ids->create('transaction'),
                    'paymentMethodId' => $this->getPrePaymentMethodId(),
                    'stateId' => $this->getStateId('open', 'order_transaction.state'),
                    'amount' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                ],
            ],
        ];

        static::getContainer()->get('order.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getStateId(string $state, string $machine): ?string
    {
        return static::getContainer()->get(Connection::class)
            ->fetchOne(
                '
                SELECT LOWER(HEX(state_machine_state.id))
                FROM state_machine_state
                    INNER JOIN  state_machine
                    ON state_machine.id = state_machine_state.state_machine_id
                    AND state_machine.technical_name = :machine
                WHERE state_machine_state.technical_name = :state
            ',
                [
                    'state' => $state,
                    'machine' => $machine,
                ]
            );
    }

    private function getPrePaymentMethodId(): string
    {
        /** @var EntityRepository<PaymentMethodCollection> $repository */
        $repository = static::getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('handlerIdentifier', PrePayment::class));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($id);

        return $id;
    }

    /**
     * @param list<array<string, string>> $additionalAddresses
     */
    private function createCustomer(array $additionalAddresses = []): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'addresses' => $additionalAddresses,
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'name' => 'Max Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        /** @var EntityRepository<CustomerCollection> $repo */
        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], Context::createDefaultContext());

        /** @var CustomerEntity|null $customer */
        $customer = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($customer);

        return $customer;
    }
}
