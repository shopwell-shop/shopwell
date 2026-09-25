<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\AbstractCartPersister;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\CartPersister;
use Shopwell\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class CartLoadRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private AbstractSalesChannelContextFactory $salesChannelFactory;

    private AbstractCartPersister $cartPersister;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->productRepository = static::getContainer()->get('product.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->cartPersister = static::getContainer()->get(CartPersister::class);
        $this->salesChannelFactory = static::getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testEmptyCart(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
        static::assertEmpty($response['errors']);
    }

    /**
     * @param array<string, string|array<string, string>>|null $ruleConditions
     */
    #[DataProvider('dataProviderPaymentMethodRule')]
    public function testFilledCart(?array $ruleConditions, int $errorCount): void
    {
        $context = $this->createPersistedCartWithProduct();

        if ($ruleConditions !== null) {
            $this->paymentMethodRepository->update([[
                'id' => $context->getPaymentMethod()->getId(),
                'availabilityRule' => [
                    'name' => 'Test Rule',
                    'priority' => 0,
                    'conditions' => [
                        $ruleConditions,
                    ],
                ],
            ]], Context::createDefaultContext());
        }

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->get('token'));

        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
        static::assertCount($errorCount, $response['errors']);
    }

    /**
     * @return array<string, array<int|array<string, string|array<string, string>>|null>>
     */
    public static function dataProviderPaymentMethodRule(): array
    {
        return [
            'No Rule' => [
                null,
                0,
            ],
            'Matching Rule' => [
                ['type' => (new AlwaysValidRule())->getName()],
                0,
            ],
            'Not Matching Rule' => [
                [
                    'type' => (new CartAmountRule())->getName(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'amount' => '-1.0',
                    ],
                ],
                1,
            ],
        ];
    }

    public function testCartIsLoadedOnceForTheContextToken(): void
    {
        $this->createPersistedCartWithProduct();

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->get('token'));

        $loadedCarts = [];
        $tracker = static function (CartLoadedEvent $event) use (&$loadedCarts): void {
            $loadedCarts[] = $event->getCart()->getToken();
        };

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $dispatcher->addListener(CartLoadedEvent::class, $tracker);

        try {
            $this->browser->request('GET', '/store-api/checkout/cart');
        } finally {
            $dispatcher->removeListener(CartLoadedEvent::class, $tracker);
        }

        static::assertSame([$this->ids->get('token')], $loadedCarts);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertNotEmpty($response['hash']);
    }

    public function testDeferredCartErrors(): void
    {
        Feature::skipTestIfInActive('DEFERRED_CART_ERRORS', $this);

        $this->productRepository->create([
            [
                'id' => $this->ids->create('productId'),
                'productNumber' => $this->ids->create('productNumber'),
                'stock' => 1,
                'isCloseout' => true,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $this->login($this->browser);

        // Add product to cart
        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    ['type' => LineItem::PRODUCT_LINE_ITEM_TYPE, 'referencedId' => $this->ids->get('productId')],
                ],
            ],
        );

        // Check that product was added to cart
        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $cartResponse['lineItems']);
        static::assertEmpty($cartResponse['errors']);

        // Set product out of stock (to force a temporary cart error)
        $this->productRepository->update([
            [
                'id' => $this->ids->create('productId'),
                'stock' => 0,
            ],
        ], Context::createDefaultContext());

        // Fetch context to simulate a non cart related request
        $this->browser->request('GET', '/store-api/context');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Fetch cart, make sure the product is no longer in cart and an error is set
        $this->browser->request('GET', '/store-api/checkout/cart');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEmpty($cartResponse['lineItems']);
        static::assertCount(1, $cartResponse['errors']);

        // Fetch cart again, error should not be present anymore
        $this->browser->request('GET', '/store-api/checkout/cart');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEmpty($cartResponse['lineItems']);
        static::assertEmpty($cartResponse['errors']);
    }

    private function createPersistedCartWithProduct(): SalesChannelContext
    {
        $this->productRepository->create([
            [
                'id' => $this->ids->create('productId'),
                'productNumber' => $this->ids->create('productNumber'),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $cart = new Cart($this->ids->create('token'));
        $cart->add(new LineItem($this->ids->create('productId'), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get('productId')));

        $context = $this->salesChannelFactory->create($this->ids->get('token'), $this->ids->get('sales-channel'));
        $this->cartPersister->save($cart, $context);

        return $context;
    }
}
