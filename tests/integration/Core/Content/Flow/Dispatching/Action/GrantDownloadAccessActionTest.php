<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopwell\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopwell\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Customer\CustomerException;
use Shopwell\Core\Checkout\Customer\SalesChannel\AbstractDownloadRoute;
use Shopwell\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopwell\Core\Content\Flow\Dispatching\Struct\ActionSequence;
use Shopwell\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopwell\Core\Content\Flow\FlowCollection;
use Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopwell\Core\Content\MailTemplate\MailTemplateTypes;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopwell\Core\Content\Media\File\FileFetcher;
use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\State;
use Shopwell\Core\Content\Test\Product\ProductBuilder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopwell\Core\Framework\Event\OrderAware;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\Stub\Framework\IdsCollection;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @internal
 */
#[Package('after-sales')]
class GrantDownloadAccessActionTest extends TestCase
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

    private SalesChannelContext $salesChannelContext;

    private OrderTransactionStateHandler $orderTransactionStateHandler;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractDownloadRoute $downloadRoute;

    private FileFetcher $fileFetcher;

    private FileSaver $fileSaver;

    private string $customerId;

    protected function setUp(): void
    {
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->flowRepository = static::getContainer()->get('flow.repository');
        $this->customerId = $this->createCustomer();
        $this->salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->orderTransactionStateHandler = static::getContainer()->get(OrderTransactionStateHandler::class);
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');
        $this->downloadRoute = static::getContainer()->get(DownloadRoute::class);
        $this->fileSaver = static::getContainer()->get(FileSaver::class);
        $this->fileFetcher = static::getContainer()->get(FileFetcher::class);
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    #[DataProvider('orderCaseProvider')]
    public function testFlowActionRunsOnEnterState(array $productDownloads): void
    {
        $orderId = $this->placeOrder($productDownloads);

        $this->assertOrderWithoutGrantedAccess($orderId, $productDownloads);

        $flowEvent = null;
        $flowListener = function (FlowSendMailActionEvent $event) use (&$flowEvent): void {
            $event = $this->onFlowSendMailActionEvent($event);

            if ($event instanceof FlowSendMailActionEvent) {
                $flowEvent = $event;
            }
        };
        $this->addEventListener($this->eventDispatcher, FlowSendMailActionEvent::class, $flowListener);

        $mailEvent = null;
        $mailListener = function (MailBeforeSentEvent $event) use (&$mailEvent): void {
            $event = $this->onMailBeforeSentEvent($event);

            if ($event instanceof MailBeforeSentEvent) {
                $mailEvent = $event;
            }
        };
        $this->addEventListener($this->eventDispatcher, MailBeforeSentEvent::class, $mailListener);

        $this->changeTransactionStateToPaid($orderId);

        $this->resetEventDispatcher();

        $this->assertDispatchedFlowEvent($productDownloads, $flowEvent);
        $this->assertDispatchedMailEvent($productDownloads, $mailEvent);

        $this->assertOrderWithGrantedAccess($orderId, $productDownloads);
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    #[DataProvider('orderCaseProvider')]
    public function testFlowActionRunsOnOrderPlaced(array $productDownloads): void
    {
        $this->cloneDefaultFlow();

        $flowEvent = null;
        $flowListener = function (FlowSendMailActionEvent $event) use (&$flowEvent): void {
            $event = $this->onFlowSendMailActionEvent($event);

            if ($event instanceof FlowSendMailActionEvent) {
                $flowEvent = $event;
            }
        };
        $this->addEventListener($this->eventDispatcher, FlowSendMailActionEvent::class, $flowListener);

        $mailEvent = null;
        $mailListener = function (MailBeforeSentEvent $event) use (&$mailEvent): void {
            $event = $this->onMailBeforeSentEvent($event);

            if ($event instanceof MailBeforeSentEvent) {
                $mailEvent = $event;
            }
        };
        $this->addEventListener($this->eventDispatcher, MailBeforeSentEvent::class, $mailListener);

        $orderId = $this->placeOrder($productDownloads);

        $this->resetEventDispatcher();

        $this->assertDispatchedFlowEvent($productDownloads, $flowEvent);
        $this->assertDispatchedMailEvent($productDownloads, $mailEvent);

        $this->assertOrderWithGrantedAccess($orderId, $productDownloads);
    }

    public static function orderCaseProvider(): \Generator
    {
        yield 'downloadable only' => [
            [
                ['foo.pdf', 'bar.pdf'],
                ['foobar.mp3'],
            ],
        ];
        yield 'mixed cart' => [
            [
                ['baz.pdf'],
                [],
            ],
        ];
    }

    public function onFlowSendMailActionEvent(FlowSendMailActionEvent $event): ?FlowSendMailActionEvent
    {
        $sequence = $event->getStorableFlow()->getFlowState()->currentSequence;

        if ($sequence instanceof ActionSequence && $sequence->action !== 'action.mail.send') {
            return null;
        }

        $event->getDataBag()->add([
            'contentHtml' => str_replace('frontend.account.order.single.download', 'store-api.account.order.single.download', (string) $event->getDataBag()->get('contentHtml')),
            'contentPlain' => str_replace('frontend.account.order.single.download', 'store-api.account.order.single.download', (string) $event->getDataBag()->get('contentPlain')),
        ]);

        return $event;
    }

    public function onMailBeforeSentEvent(MailBeforeSentEvent $event): ?MailBeforeSentEvent
    {
        $data = $event->getData();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplates.id', $data['templateId']));

        $type = static::getContainer()->get('mail_template_type.repository')->search($criteria, $event->getContext())->getEntities()->first();

        if (!$type instanceof MailTemplateTypeEntity || $type->getTechnicalName() !== MailTemplateTypes::MAILTYPE_DOWNLOADS_DELIVERY) {
            return null;
        }

        return $event;
    }

    /**
     * @param array<int, string[]>|null $productDownloads
     */
    private function placeOrder(?array $productDownloads = null): string
    {
        $productDownloads ??= [[]];

        $cart = $this->cartService->createNew($this->salesChannelContext->getToken());
        $cart = $this->addProducts($cart, $productDownloads);

        $orderId = $this->cartService->order($cart, $this->salesChannelContext, new RequestDataBag());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        return $orderId;
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    private function assertOrderWithoutGrantedAccess(string $orderId, array $productDownloads): string
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.downloads');
        $criteria->addAssociation('deliveries');

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        static::assertCount(\count($productDownloads), $lineItems);
        static::assertTrue($lineItems->hasLineItemWithType(ProductDefinition::TYPE_DIGITAL));

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertTrue($lineItems->hasLineItemWithState(State::IS_DOWNLOAD));
        }

        foreach ($productDownloads as $key => $downloadFiles) {
            $lineItem = $lineItems->getAt($key);
            static::assertNotNull($lineItem);
            static::assertNotNull($lineItem->getDownloads());
            static::assertCount(\count($downloadFiles), $lineItem->getDownloads());
            foreach ($lineItem->getDownloads() as $download) {
                static::assertFalse($download->isAccessGranted());

                try {
                    $request = new Request([], [], ['downloadId' => $download->getId(), 'orderId' => $orderId]);
                    $this->downloadRoute->load($request, $this->salesChannelContext);

                    static::fail('Download route returned response without access granted');
                } catch (\Throwable $exception) {
                    static::assertInstanceOf(CustomerException::class, $exception);
                    static::assertSame(\sprintf('Line item download file with id "%s" not found.', $download->getId()), $exception->getMessage());
                }
            }
        }

        static::assertNotNull($order->getDeliveries());
        if (\in_array([], $productDownloads, true)) {
            static::assertNotNull($order->getLineItems());
            static::assertTrue($lineItems->hasLineItemWithType(ProductDefinition::TYPE_PHYSICAL));

            if (!Feature::isActive('v6.8.0.0')) {
                static::assertTrue($order->getLineItems()->hasLineItemWithState(State::IS_PHYSICAL));
            }
            static::assertCount(1, $order->getDeliveries());
        } else {
            static::assertCount(0, $order->getDeliveries());
        }

        return $orderId;
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    private function assertOrderWithGrantedAccess(string $orderId, array $productDownloads): void
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems.downloads.media');

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        static::assertCount(\count($productDownloads), $lineItems);
        static::assertTrue($lineItems->hasLineItemWithType(ProductDefinition::TYPE_DIGITAL));

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertTrue($lineItems->hasLineItemWithState(State::IS_DOWNLOAD));
        }

        foreach ($productDownloads as $key => $downloadFiles) {
            $lineItem = $lineItems->getAt($key);
            static::assertNotNull($lineItem);
            static::assertNotNull($lineItem->getDownloads());
            static::assertCount(\count($downloadFiles), $lineItem->getDownloads());
            foreach ($lineItem->getDownloads() as $download) {
                static::assertTrue($download->isAccessGranted());
                static::assertNotNull($download->getMedia());

                $request = new Request([], [], ['downloadId' => $download->getId(), 'orderId' => $orderId]);
                $response = $this->downloadRoute->load($request, $this->salesChannelContext);
                static::assertInstanceOf(StreamedResponse::class, $response);
                ob_start();
                $response->send();
                $content = ob_get_clean();
                static::assertSame($download->getMediaId(), $content);
            }
        }
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    private function assertDispatchedFlowEvent(array $productDownloads, ?FlowSendMailActionEvent $flowEvent): void
    {
        static::assertInstanceOf(FlowSendMailActionEvent::class, $flowEvent);
        $order = $flowEvent->getStorableFlow()->getData(OrderAware::ORDER);

        static::assertInstanceOf(OrderEntity::class, $order);
        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        $lineItems->sortByPosition();
        foreach ($productDownloads as $key => $files) {
            static::assertNotNull($lineItems->getAt($key));
            static::assertNotNull($lineItems->getAt($key)->getDownloads());
            static::assertCount(\count($files), $lineItems->getAt($key)->getDownloads());
            foreach ($lineItems->getAt($key)->getDownloads() as $download) {
                static::assertTrue($download->isAccessGranted());
            }
        }
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    private function assertDispatchedMailEvent(array $productDownloads, ?MailBeforeSentEvent $mailEvent): void
    {
        static::assertInstanceOf(MailBeforeSentEvent::class, $mailEvent);

        $files = array_merge(...$productDownloads);
        foreach ($files as $file) {
            static::assertIsString($mailEvent->getMessage()->getTextBody());
            static::assertStringContainsString($file, $mailEvent->getMessage()->getTextBody());
            static::assertIsString($mailEvent->getMessage()->getHtmlBody());
            static::assertStringContainsString($file, $mailEvent->getMessage()->getHtmlBody());
        }
    }

    /**
     * @param array<int, string[]> $productDownloads
     */
    private function addProducts(Cart $cart, array $productDownloads): Cart
    {
        $ids = new IdsCollection();
        $taxIds = $this->salesChannelContext->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));
        $products = [];

        foreach ($productDownloads as $key => $downloadFiles) {
            $products[] = (new ProductBuilder($ids, 'product-' . $key))
                ->price(1.0)
                ->tax('t1')
                ->visibility()
                ->type($downloadFiles === [] ? ProductDefinition::TYPE_PHYSICAL : ProductDefinition::TYPE_DIGITAL)
                ->add('downloads', array_map(static function (string $file): array {
                    [$fileName, $fileExtension] = explode('.', $file);

                    return [
                        'media' => [
                            'id' => Uuid::randomHex(),
                            'fileName' => $fileName,
                            'fileExtension' => $fileExtension,
                            'path' => 'media/' . $fileName . '.' . $fileExtension,
                            'private' => true,
                        ],
                    ];
                }, $downloadFiles))
                ->build();
        }

        $this->salesChannelContext->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($products): void {
            $this->productRepository->create($products, $context);
            $downloads = array_merge(...array_column($products, 'downloads'));

            foreach ($downloads as $download) {
                $media = $download['media'];
                $mediaFile = $this->fileFetcher->fetchBlob($media['id'], $media['fileExtension'], '');
                $this->fileSaver->persistFileToMedia($mediaFile, $media['fileName'], $media['id'], $context);
                $this->fileFetcher->cleanUpTempFile($mediaFile);
            }
        });

        foreach ($ids->prefixed('product-') as $id) {
            $cart = $this->addProduct($id, 1, $cart, $this->cartService, $this->salesChannelContext);
        }

        return $cart;
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

    private function cloneDefaultFlow(): void
    {
        $flowId = $this->flowRepository
            ->searchIds(
                (new Criteria())
                    ->addFilter(new EqualsFilter('name', 'Deliver ordered product downloads')),
                $this->salesChannelContext->getContext()
            )->firstId();
        static::assertNotNull($flowId);

        $behavior = new CloneBehavior([
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
        ]);
        $this->flowRepository->clone($flowId, $this->salesChannelContext->getContext(), null, $behavior);
    }

    private function addProduct(string $productId, int $quantity, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());
        $product = $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => $quantity], $context);

        return $cartService->add($cart, $product, $context);
    }

    private function createDefaultSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, [SalesChannelContextService::CUSTOMER_ID => $this->customerId]);
    }
}
