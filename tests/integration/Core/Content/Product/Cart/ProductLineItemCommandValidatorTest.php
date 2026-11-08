<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Product\Cart;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\Exception\ProductLineItemDifferentIdException;
use Shopwell\Core\Content\Product\Exception\ProductLineItemInconsistentException;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Sync\SyncBehavior;
use Shopwell\Core\Framework\Api\Sync\SyncOperation;
use Shopwell\Core\Framework\Api\Sync\SyncService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('inventory')]
class ProductLineItemCommandValidatorTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $repository;

    private CartService $cartService;

    private AbstractSalesChannelContextFactory $contextFactory;

    private SalesChannelContext $context;

    /**
     * @var EntityRepository<OrderLineItemCollection>
     */
    private EntityRepository $lineItemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get('product.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->lineItemRepository = static::getContainer()->get('order_line_item.repository');
        $this->addCountriesToSalesChannel();

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );
    }

    public function testOrderProduct(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        $first = $lineItems->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $first);

        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $first->getType());
    }

    public function testUpdateLineItemQuantity(): void
    {
        $id = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        $first = $lineItems->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $first);

        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'quantity' => 10],
        ], $context);

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();
        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());
        static::assertSame(10, $first->getQuantity());
    }

    public function testUpdateFailsIfProductNumberIsMissing(): void
    {
        $id = $this->createProduct();
        $secondId = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);

        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        $this->expectExceptionObject(
            (new WriteException())
                ->add(new ProductLineItemDifferentIdException($first->getId()))
                ->add(new ProductLineItemInconsistentException($first->getId()))
        );

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'productId' => $secondId],
        ], $context);
    }

    public function testSwitchLineItemProduct(): void
    {
        $id = $this->createProduct();
        $secondId = $this->createProduct();

        $context = Context::createDefaultContext();

        $orderId = $this->orderProduct($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getProductId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('productNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'productId' => $secondId, 'referencedId' => $secondId, 'payload' => ['productNumber' => $secondId]],
        ], $context);
    }

    public function testALineItemCanSwitchProductAcrossTwoWritesInOneRequest(): void
    {
        $id = $this->createProduct();
        $secondId = $this->createProduct();
        $orderId = $this->orderProduct($id, 1, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $lineItem = $this->lineItemRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($lineItem);

        static::getContainer()->get(SyncService::class)->sync([
            new SyncOperation('ids', 'order_line_item', SyncOperation::ACTION_UPSERT, [['id' => $lineItem->getId(), 'productId' => $secondId, 'referencedId' => $secondId]]),
            new SyncOperation('payload', 'order_line_item', SyncOperation::ACTION_UPSERT, [['id' => $lineItem->getId(), 'payload' => ['productNumber' => $secondId]]]),
        ], Context::createDefaultContext(), new SyncBehavior());

        $lineItem = $this->lineItemRepository->search(new Criteria([$lineItem->getId()]), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($lineItem);
        static::assertSame($secondId, $lineItem->getProductId());
    }

    public function testAnOrderedProductCanBeDeleted(): void
    {
        $id = $this->createProduct();
        $orderId = $this->orderProduct($id, 1, $this->context);

        $this->repository->delete([['id' => $id]], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $lineItem = $this->lineItemRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($lineItem);
        static::assertNull($lineItem->getProductId());
    }

    private function orderProduct(string $id, int $quantity, SalesChannelContext $context): string
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create(['id' => $id, 'referencedId' => $id, 'quantity' => $quantity], $context), $context);

        $item = $cart->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertSame($quantity, $item->getQuantity());

        return $this->cartService->order($cart, $context, new RequestDataBag());
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'gender' => $this->getValidGender(),
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
                    'gender' => $this->getValidGender(),
                    'name' => 'Max Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('customer.repository')
            ->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @param array<string|int, mixed|null> $config
     */
    private function createProduct(array $config = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $config);

        $this->repository->create([$product], Context::createDefaultContext());
        $this->addTaxDataToSalesChannel($this->context, $product['tax']);

        return $id;
    }
}
