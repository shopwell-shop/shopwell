<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Order\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\LineItem\LineItem;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\SalesChannel\CartService;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopwell\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopwell\Core\Checkout\Customer\CustomerEntity;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Checkout\Order\SalesChannel\OrderService;
use Shopwell\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopwell\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopwell\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopwell\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopwell\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\Test\TestDefaults;
use Shopwell\Storefront\Controller\AccountOrderController;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @internal
 */
#[Package('checkout')]
class OrderServiceTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    private OrderService $orderService;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = static::getContainer()->get(OrderService::class);

        $this->orderRepository = static::getContainer()->get('order.repository');

        $this->cleanDefaultSalesChannelDomain();
        $this->addCountriesToSalesChannel();

        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create(
            '',
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer('Jon', 'Doe')]
        );
    }

    public function testOrderDeliveryStateTransition(): void
    {
        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        $deliveries = $order->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $orderDeliveryId = $delivery->getId();

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'ship',
            new RequestDataBag(),
            $this->salesChannelContext->getContext()
        );

        $updatedOrder = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($updatedOrder);
        $deliveries = $updatedOrder->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        static::assertNotNull($delivery->getStateMachineState());
        $updatedDeliveryState = $delivery->getStateMachineState()->getTechnicalName();

        static::assertSame('shipped', $updatedDeliveryState);
    }

    public function testOrderDeliveryStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        $deliveries = $order->getDeliveries();
        static::assertNotNull($deliveries);
        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('The new status is as follows: Cancelled.', $htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(),
            $this->salesChannelContext->getContext()
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testSkipOrderDeliveryStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('The new status is as follows: Cancelled.', $htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->salesChannelContext
            ->getContext()
            ->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true, [], []));

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(['sendMail' => false]),
            $this->salesChannelContext->getContext()
        );

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertFalse($eventDidRun, 'The mail.sent Event did run');
    }

    public function testOrderDeliveryStateTransitionSendsMailZh(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $previousContext = $this->salesChannelContext;
        $this->salesChannelContext = $contextFactory->create(
            '',
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer('Jon', 'De'),
                SalesChannelContextService::LANGUAGE_ID => $this->getZhCnLanguageId(),
            ]
        );
        $orderId = $this->performOrder();

        // getting the id of the order delivery
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());
        $orderDeliveryId = $delivery->getId();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, $this->getZhCnLanguageId());

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $innerEvent = null;

        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, &$innerEvent): void {
            $innerEvent = $event;
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->orderDeliveryStateTransition(
            $orderDeliveryId,
            'cancel',
            new RequestDataBag(),
            Context::createDefaultContext() // DefaultContext is intended to test if the language of the order is used
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertNotNull($innerEvent);
        $textHtml = $innerEvent->getContents()['text/html'];
        static::assertIsString($textHtml);
        static::assertStringContainsString('订单当前的配送状态：已取消。', $textHtml);
        static::assertStringContainsString($url, $textHtml);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
        $this->salesChannelContext = $previousContext;
    }

    public function testOrderTransactionStateTransition(): void
    {
        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('transactions.stateMachineState');

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'remind',
            new RequestDataBag(),
            $this->salesChannelContext->getContext()
        );

        $updatedOrder = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($updatedOrder);
        static::assertNotNull($transactions = $updatedOrder->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        static::assertNotNull($transaction->getStateMachineState());
        $updatedTransactionState = $transaction->getStateMachineState()->getTechnicalName();

        static::assertSame('reminded', $updatedTransactionState);
    }

    public function testOrderTransactionStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'transactions.stateMachineState',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('The new status is as follows: Paid (partially).', $htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'paid_partially',
            new RequestDataBag(),
            $this->salesChannelContext->getContext()
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testSkipOrderTransactionStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $orderId = $this->performOrder();

        // getting the id of the order transaction
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations([
            'stateMachineState',
            'transactions.stateMachineState',
        ]);

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());
        $orderTransactionId = $transaction->getId();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('The new status is as follows: Paid (partially).', $htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->salesChannelContext
            ->getContext()
            ->addExtension(SendMailAction::MAIL_CONFIG_EXTENSION, new MailSendSubscriberConfig(true, [], []));

        $this->orderService->orderTransactionStateTransition(
            $orderTransactionId,
            'paid_partially',
            new RequestDataBag(['sendMail' => false]),
            $this->salesChannelContext->getContext()
        );

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertFalse($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testCreateOrder(): void
    {
        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        $orderId = $this->orderService->createOrder($data, $this->salesChannelContext);

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $newlyCreatedOrder);
        static::assertSame($orderId, $newlyCreatedOrder->getId());
    }

    public function testCreateOrderSavesVatIdsInOrderCustomer(): void
    {
        $vatIds = ['DE123456789'];
        $additionalData = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => $vatIds,
        ];
        $contextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $contextFactory->create(
            '',
            TestDefaults::SALES_CHANNEL,
            [SalesChannelContextService::CUSTOMER_ID => $this->createCustomer('Jon', 'Doe', $additionalData)]
        );

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        $orderId = $this->orderService->createOrder($data, $this->salesChannelContext);

        $criteria = new Criteria([$orderId]);

        $newlyCreatedOrder = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();

        static::assertInstanceOf(OrderEntity::class, $newlyCreatedOrder);
        static::assertSame($orderId, $newlyCreatedOrder->getId());
        $orderCustomer = $newlyCreatedOrder->getOrderCustomer();
        static::assertNotNull($orderCustomer);
        static::assertSame($vatIds, $orderCustomer->getVatIds());
    }

    public function testCreateOrderSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = static function () use (&$eventDidRun): void {
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->createOrder($data, $this->salesChannelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testOrderStateTransition(): void
    {
        $orderId = $this->performOrder();

        $this->orderService->orderStateTransition($orderId, 'cancel', new ParameterBag(), $this->salesChannelContext->getContext());

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $cancelledOrder = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($cancelledOrder);
        $state = $cancelledOrder->getStateMachineState();

        static::assertNotNull($state);
        static::assertSame('cancelled', $state->getTechnicalName());
    }

    public function testOrderStateTransitionSendsMail(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $orderId = $this->performOrder();

        $domain = 'http://shopwell.' . Uuid::randomHex();
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $criteria = new Criteria([$orderId]);

        $criteria->addAssociation('stateMachineState');

        $order = $this->orderRepository->search($criteria, $this->salesChannelContext->getContext())->getEntities()->first();
        static::assertNotNull($order);

        $url = $domain . '/account/order/' . $order->getDeepLinkCode();
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString('The new status is as follows: Cancelled.', $htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->orderStateTransition($orderId, 'cancel', new ParameterBag(), $this->salesChannelContext->getContext());
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testMailTemplateHasCorrectDomain(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        $firstDomain = 'http://shopwell.first-domain';
        $this->setDomainForSalesChannel($firstDomain, Defaults::LANGUAGE_SYSTEM);

        $languageRepository = static::getContainer()->get('language.repository');

        $criteria = new Criteria();
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM),
                ]
            )
        );

        $languageId = $languageRepository->searchIds($criteria, $this->salesChannelContext->getContext())->firstId();
        static::assertNotNull($languageId);
        $secondDomain = 'http://shopwell.second-domain';
        $this->setDomainForSalesChannel($secondDomain, $languageId);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = function (MailSentEvent $event) use (&$eventDidRun, $firstDomain, $secondDomain): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString($firstDomain, $htmlText);
            static::assertThat($htmlText, $this->logicalNot($this->stringContains($secondDomain)));
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->createOrder($data, $this->salesChannelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    public function testMailTemplateHandlesVirtualDomains(): void
    {
        if (!static::getContainer()->has(AccountOrderController::class)) {
            // ToDo: NEXT-16882 - Reactivate tests again
            static::markTestSkipped('Order mail tests should be fixed without storefront in NEXT-16882');
        }

        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        $domain = 'http://shopwell.test/virtual-domain';
        $this->setDomainForSalesChannel($domain, Defaults::LANGUAGE_SYSTEM);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $url = $domain . '/account/order';
        $eventDidRun = false;
        $listenerClosure = static function (MailSentEvent $event) use (&$eventDidRun, $url): void {
            $htmlText = $event->getContents()['text/html'];
            self::assertIsString($htmlText);
            static::assertStringContainsString($url, $htmlText);
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MailSentEvent::class, $listenerClosure);

        $this->orderService->createOrder($data, $this->salesChannelContext);
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        $dispatcher->removeListener(MailSentEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The mail.sent Event did not run');
    }

    private function performOrder(): string
    {
        $data = new RequestDataBag(['tos' => true]);
        $this->fillCart($this->salesChannelContext->getToken());

        return $this->orderService->createOrder($data, $this->salesChannelContext);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createCustomer(string $firstName, string $lastName, array $options = []): string
    {
        $customerId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $customerId,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'city' => 'Schöppingen',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'salutationId' => $salutationId,
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $customerId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'salutationId' => $salutationId,
            'customerNumber' => '12345',
        ];

        $customer = array_merge_recursive($customer, $options);

        static::getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function fillCart(string $contextToken): void
    {
        $cart = static::getContainer()->get(CartService::class)->createNew($contextToken);

        $productId = $this->createProduct();
        $cart->add(new LineItem('lineItem1', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));
        $cart->setTransactions($this->createTransaction());
    }

    private function createProduct(): string
    {
        $productId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => '123456789',
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 19.99, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $productId, 'name' => 'Shopwell'],
            'tax' => ['id' => $this->getValidTaxId(), 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $productId;
    }

    private function createTransaction(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getValidPaymentMethodId()
            ),
        ]);
    }

    private function setDomainForSalesChannel(string $domain, string $languageId): void
    {
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $data = [
            'id' => TestDefaults::SALES_CHANNEL,
            'domains' => [[
                'languageId' => $languageId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $domain,
            ]],
        ];

        $salesChannelRepository->update([$data], $this->salesChannelContext->getContext());
    }

    private function cleanDefaultSalesChannelDomain(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->delete(SalesChannelDomainDefinition::ENTITY_NAME, [
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
        ]);
    }
}
