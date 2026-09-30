<?php
declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Checkout\Payment\Controller;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopwell\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopwell\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopwell\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopwell\Core\Checkout\Order\OrderCollection;
use Shopwell\Core\Checkout\Payment\Cart\Token\JWTFactoryV2;
use Shopwell\Core\Checkout\Payment\Cart\Token\PaymentToken;
use Shopwell\Core\Checkout\Payment\Cart\Token\PaymentTokenGenerator;
use Shopwell\Core\Checkout\Payment\Cart\Token\PaymentTokenLifecycle;
use Shopwell\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentProcessor;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Feature;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopwell\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopwell\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopwell\Core\Test\Integration\Traits\OrderFixture;
use Shopwell\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private JWTFactoryV2 $tokenFactory;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private PaymentProcessor $paymentProcessor;

    private ?string $orderCustomerId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Feature::isActive('v6.8.0.0')) {
            $this->tokenFactory = static::getContainer()->get(JWTFactoryV2::class);
        }

        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->paymentProcessor = static::getContainer()->get(PaymentProcessor::class);
    }

    public function testCallWithoutToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);

        if (!Feature::isActive('v6.8.0.0')) {
            static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
        } else {
            static::assertSame('CHECKOUT__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
        }
    }

    public function testCallWithInvalidToken(): void
    {
        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=abc');

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValidTokenWithInvalidOrder(): void
    {
        $client = $this->getBrowser();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = Uuid::randomHex();
            $paymentToken->transactionId = Uuid::randomHex();
            $paymentToken->salesChannelId = TestDefaults::SALES_CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, Uuid::randomHex(), Uuid::randomHex(), 'testFinishUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        static::assertIsString($client->getResponse()->getContent());
        $response = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $response['errors'][0]['code']);
    }

    public function testValid(): void
    {
        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->salesChannelId = TestDefaults::SALES_CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testFinishUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    public function testCancelledPayment(): void
    {
        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->salesChannelId = TestDefaults::SALES_CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $paymentToken->errorUrl = 'testErrorUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl', null, 'testErrorUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();

        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token . '&cancel=1');

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testErrorUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    public function testCallTwice(): void
    {
        Feature::skipTestIfInActive('REPEATED_PAYMENT_FINALIZE', $this);

        $transaction = $this->createValidOrderTransaction();

        if (Feature::isActive('v6.8.0.0')) {
            $paymentToken = new PaymentToken();
            $paymentToken->jti = Uuid::randomHex();
            $paymentToken->paymentMethodId = $transaction->getPaymentMethodId();
            $paymentToken->transactionId = $transaction->getId();
            $paymentToken->salesChannelId = TestDefaults::SALES_CHANNEL;
            $paymentToken->finishUrl = 'testFinishUrl';
            $token = static::getContainer()->get(PaymentTokenGenerator::class)->encode($paymentToken);
            static::getContainer()->get(PaymentTokenLifecycle::class)->addToken($paymentToken->jti, $paymentToken->exp ?? new \DateTimeImmutable());
        } else {
            $tokenStruct = new TokenStruct(null, null, $transaction->getPaymentMethodId(), $transaction->getId(), 'testFinishUrl', null, 'testErrorUrl');
            $token = $this->tokenFactory->generateToken($tokenStruct);
        }

        $client = $this->getBrowser();
        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);
        $client->request('GET', '/payment/finalize-transaction?_sw_payment_token=' . $token);

        $response = $client->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('testFinishUrl', $response->getTargetUrl());
        static::assertTrue($response->isRedirection());
    }

    private function getBrowser(): KernelBrowser
    {
        return KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false);
    }

    private function getSalesChannelContext(string $paymentMethodId): SalesChannelContext
    {
        $options = [
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
        ];
        if ($this->orderCustomerId !== null) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $this->orderCustomerId;
        }

        return static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL, $options);
    }

    private function createTransaction(
        string $orderId,
        string $paymentMethodId,
        Context $context
    ): string {
        $id = Uuid::randomHex();
        $transaction = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentMethodId' => $paymentMethodId,
            'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE),
            'amount' => new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), 1),
            'payload' => '{}',
        ];

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $id;
    }

    private function createOrder(Context $context): string
    {
        $orderId = Uuid::randomHex();
        $this->orderCustomerId = Uuid::randomHex();

        $order = $this->getOrderData($orderId, $context, $this->orderCustomerId);
        $this->orderRepository->upsert($order, $context);

        return $orderId;
    }

    private function createPaymentMethod(
        Context $context,
        string $handlerIdentifier = TestPaymentHandler::class
    ): string {
        $id = Uuid::randomHex();
        $payment = [
            'id' => $id,
            'handlerIdentifier' => $handlerIdentifier,
            'name' => 'Test Payment',
            'technicalName' => 'payment_test',
            'description' => 'Test payment handler',
            'active' => true,
        ];

        $this->paymentMethodRepository->upsert([$payment], $context);

        return $id;
    }

    private function createValidOrderTransaction(): OrderTransactionEntity
    {
        $context = Context::createDefaultContext();

        $paymentMethodId = $this->createPaymentMethod($context);
        $orderId = $this->createOrder($context);
        $transactionId = $this->createTransaction($orderId, $paymentMethodId, $context);

        $salesChannelContext = $this->getSalesChannelContext($paymentMethodId);

        $response = $this->paymentProcessor->pay($orderId, new Request(), $salesChannelContext);

        static::assertNotNull($response);
        static::assertSame(TestPaymentHandler::REDIRECT_URL, $response->getTargetUrl());

        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);
        $transaction->setPaymentMethodId($paymentMethodId);
        $transaction->setOrderId($orderId);
        $transaction->setStateId(Uuid::randomHex());

        return $transaction;
    }
}
