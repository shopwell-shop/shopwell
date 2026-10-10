<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Flow\Dispatching;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemRule;
use Shopwell\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\CustomerCollection;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopwell\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopwell\Core\Checkout\Customer\Rule\NameRule;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderDefinition;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\Action\AddCustomerTagAction;
use Shopwell\Core\Content\Flow\Dispatching\Action\AddOrderTagAction;
use Shopwell\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopwell\Core\Content\Flow\Dispatching\FlowDispatcher;
use Shopwell\Core\Content\Flow\FlowCollection;
use Shopwell\Core\Content\Flow\Rule\OrderTagRule;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\Tag\TagCollection;
use Shopwell\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('after-sales')]
class FlowExecutorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private CartService $cartService;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    /**
     * @var EntityRepository<TagCollection>
     */
    private EntityRepository $tagRepository;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private FlowDispatcher $flowDispatcher;

    private SalesChannelContext $salesChannelContext;

    private string $customerId;

    protected function setUp(): void
    {
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->orderTransactionStateHandler = static::getContainer()->get(OrderTransactionStateHandler::class);
        $this->flowRepository = static::getContainer()->get('flow.repository');
        $this->tagRepository = static::getContainer()->get('tag.repository');
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->flowDispatcher = static::getContainer()->get(FlowDispatcher::class);
        $this->customerId = $this->createCustomer();
        $this->salesChannelContext = $this->createDefaultSalesChannelContext();
    }

    public function testFlowExecutesWithIfSequencesEvaluated(): void
    {
        $ids = new IdsCollection();

        $this->createTags($ids);

        $this->createFlow($ids);

        $this->placeOrder($ids);

        $this->orderRepository->update([
            [
                'id' => $ids->get('order'),
                'tags' => [
                    ['id' => $ids->get('tag-1')],
                ],
            ],
        ], $this->salesChannelContext->getContext());

        $product = (new ProductBuilder($ids, 'product'))->price(50)->build();

        unset($product['type']);

        $this->productRepository->update([$product], $this->salesChannelContext->getContext());

        $this->changeTransactionStateToPaid($ids->get('order'));

        $criteria = new Criteria([$ids->get('order')]);
        $criteria->addAssociation('tags');

        $order = $this->orderRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $order);
        static::assertInstanceOf(TagCollection::class, $order->getTags());
        static::assertContains($ids->get('tag-1'), $order->getTags()->getIds());
        static::assertContains($ids->get('tag-2'), $order->getTags()->getIds());
    }

    public function testCustomerAwareFlowExecutesWithIfSequencesEvaluated(): void
    {
        $ids = new IdsCollection();

        $this->createTags($ids);

        $this->createCustomerAwareFlow($ids);

        $this->dispatchCustomerDoubleOptInRegistrationEvent($ids);

        $criteria = new Criteria([$ids->get('customer-1')]);
        $criteria->addAssociation('tags');

        $customer = $this->customerRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertInstanceOf(TagCollection::class, $customer->getTags());
        static::assertContains($ids->get('tag-1'), $customer->getTags()->getIds());
    }

    private function placeOrder(IdsCollection $ids): void
    {
        $cart = $this->cartService->createNew($this->salesChannelContext->getToken());
        $cart = $this->addProducts($cart, $ids);

        $ids->set('order', $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag()));
    }

    private function addProducts(Cart $cart, IdsCollection $ids): Cart
    {
        $taxIds = $this->salesChannelContext->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));

        $this->productRepository->create([
            (new ProductBuilder($ids, 'product'))
                ->price(100)
                ->tax('t1')
                ->visibility()
                ->add('height', 3000)
                ->add('width', 3000)
                ->add('length', 3000)
                ->build(),
        ], $this->salesChannelContext->getContext());

        return $this->addProductToCart($ids->get('product'), 1, $cart, $this->cartService, $this->salesChannelContext);
    }

    private function changeTransactionStateToPaid(string $orderId): void
    {
        $transaction = $this->orderTransactionRepository
            ->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('orderId', $orderId))
                    ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
                $this->salesChannelContext->getContext()
            )->getEntities()->first();
        static::assertInstanceOf(OrderTransactionEntity::class, $transaction);

        $this->orderTransactionStateHandler->paid($transaction->getId(), $this->salesChannelContext->getContext());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    private function createTags(IdsCollection $idsCollection): void
    {
        $tags = [
            [
                'id' => $idsCollection->get('tag-1'),
                'name' => 'foo',
            ],
            [
                'id' => $idsCollection->get('tag-2'),
                'name' => 'bar',
            ],
        ];

        $this->tagRepository->create($tags, $this->salesChannelContext->getContext());
    }

    private function createFlow(IdsCollection $idsCollection): void
    {
        $this->flowRepository->create([
            [
                'name' => 'On enter paid state',
                'eventName' => 'state_enter.order_transaction.state.paid',
                'priority' => 10,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $idsCollection->get('sequence-1'),
                        'parentId' => null,
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'name' => 'Test order rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new OrderTagRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('tag-1')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-2'),
                        'parentId' => $idsCollection->get('sequence-1'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test line item rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemRule())->getName(),
                                    'value' => [
                                        'identifiers' => [$idsCollection->get('product')],
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-3'),
                        'parentId' => $idsCollection->get('sequence-2'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test customer rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new NameRule())->getName(),
                                    'value' => [
                                        'name' => 'Max Mustermann',
                                        'operator' => OrderTagRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-4'),
                        'parentId' => $idsCollection->get('sequence-3'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test cart rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new CartVolumeRule())->getName(),
                                    'value' => [
                                        'volume' => 8,
                                        'operator' => CartVolumeRule::OPERATOR_GT,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => $idsCollection->get('sequence-5'),
                        'parentId' => $idsCollection->get('sequence-4'),
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'trueCase' => true,
                        'rule' => [
                            'name' => 'Test price rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new LineItemTotalPriceRule())->getName(),
                                    'value' => [
                                        'amount' => 100,
                                        'operator' => LineItemTotalPriceRule::OPERATOR_GTE,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'parentId' => $idsCollection->get('sequence-5'),
                        'ruleId' => null,
                        'actionName' => AddOrderTagAction::getName(),
                        'config' => [
                            'tagIds' => [$idsCollection->get('tag-2') => 'bar'],
                            'entity' => OrderDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], $this->salesChannelContext->getContext());
    }

    private function addProductToCart(string $productId, int $quantity, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());
        $product = $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $product, $context);
    }

    private function createCustomerAwareFlow(IdsCollection $idsCollection): void
    {
        $this->flowRepository->create([
            [
                'name' => 'On customer double opt in registration',
                'eventName' => CustomerDoubleOptInRegistrationEvent::EVENT_NAME,
                'priority' => 10,
                'active' => true,
                'sequences' => [
                    [
                        'id' => $idsCollection->get('sequence-1'),
                        'parentId' => null,
                        'actionName' => null,
                        'config' => [],
                        'position' => 1,
                        'rule' => [
                            'name' => 'Test customer requested group rule',
                            'priority' => 1,
                            'conditions' => [
                                [
                                    'type' => (new CustomerGroupRule())->getName(),
                                    'value' => [
                                        'customerGroupIds' => [$idsCollection->get('customer-group')],
                                        'operator' => CustomerGroupRule::OPERATOR_EQ,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'parentId' => $idsCollection->get('sequence-1'),
                        'ruleId' => null,
                        'actionName' => AddCustomerTagAction::getName(),
                        'config' => [
                            'tagIds' => [$idsCollection->get('tag-1') => 'bar'],
                            'entity' => CustomerDefinition::ENTITY_NAME,
                        ],
                        'position' => 1,
                        'trueCase' => true,
                    ],
                ],
            ],
        ], $this->salesChannelContext->getContext());
    }

    private function dispatchCustomerDoubleOptInRegistrationEvent(IdsCollection $ids): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext(false);

        static::assertNull($salesChannelContext->getCustomer());

        $customer = (new CustomerBuilder(
            $ids,
            'customer-1'
        ))->build();

        $this->customerRepository->create([$customer], $salesChannelContext->getContext());

        $customer = $this->customerRepository->search(
            new Criteria([$ids->get('customer-1')]),
            $salesChannelContext->getContext()
        )->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $event = new CustomerDoubleOptInRegistrationEvent(
            $customer,
            $salesChannelContext,
            ''
        );

        $this->flowDispatcher->dispatch($event);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();
    }

    private function createDefaultSalesChannelContext(bool $withCustomer = true): SalesChannelContext
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        if ($withCustomer === false) {
            return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        }

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $this->customerId]);
    }
}
