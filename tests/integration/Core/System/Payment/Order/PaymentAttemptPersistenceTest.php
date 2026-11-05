<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\Payment\Order;

use Payum\Core\GatewayInterface;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentOrderException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentHandover;
use Shopwell\Core\System\Payment\Gateway\Struct\PaymentStartResult;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Notification\PaymentNotifyUrlBuilder;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderRowLock;
use Shopwell\Core\System\Payment\Order\PaymentOrderService;
use Shopwell\Core\System\Payment\Order\PaymentOrderStarter;
use Shopwell\Core\System\Payment\Order\Struct\PaymentOrderRequest;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\Capability\PaymentCapability;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\DefaultPaymentRoutingPolicy;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentProductEligibility;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;
use Shopwell\Core\System\Payment\Routing\PaymentRouteResolver;

/**
 * @internal
 */
#[Package('framework')]
class PaymentAttemptPersistenceTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testPersistedClaimPreventsReentryAndReplaysTheOriginalHandover(): void
    {
        [$app, $accountId] = $this->merchant();
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $starter = $this->starter();
        $gateway = static::createMock(GatewayInterface::class);
        $route = $this->route($gateway, $accountId);
        $order = $orders->create($app, new PaymentOrderRequest(
            outTradeNo: 'obligation-' . Uuid::randomHex(),
            amount: 1000,
            currency: 'CNY',
            subject: 'Attempt persistence',
        ));
        $gateway->expects($this->once())->method('execute')->willReturnCallback(function (Capture $capture) use ($orders, $starter, $app, $order, $route, $accountId): void {
            $claimed = $orders->getById($order->id);
            static::assertNotNull($claimed->activeAttempt);
            static::assertSame(PaymentAttemptEntity::STATUS_PENDING, $claimed->activeAttempt->status);
            static::assertSame($accountId, $claimed->activeAttempt->accountId);
            static::assertSame($claimed->activeAttempt->attemptNo, $capture->getModel()[PaymentGatewayModel::PAYMENT_NO]);
            static::assertNotSame($claimed->paymentNo, $claimed->activeAttempt->attemptNo);
            try {
                $starter->start($app, $claimed, $route);
                static::fail('A second request must see the persisted claim.');
            } catch (PaymentOrderException $exception) {
                static::assertSame(PaymentOrderException::startPending($order->paymentNo)->getErrorCode(), $exception->getErrorCode());
            }
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/original'), 'original-trade');
        });

        $starter->start($app, $order, $route);
        $persisted = $orders->getById($order->id);
        static::assertNotNull($persisted->activeAttempt);
        static::assertSame(PaymentAttemptEntity::STATUS_HANDOVER, $persisted->activeAttempt->status);
        static::assertSame(['appid' => 'frozen-application'], $persisted->activeAttempt->configuration['params']);
        static::assertCount(1, $persisted->attempts ?? []);
        static::assertCount(1, $persisted->transactions ?? []);
        static::assertSame($persisted->activeAttemptId, array_values($persisted->transactions ?? [])[0]->paymentAttemptId);
        $result = $starter->replay($persisted);
        static::assertNotNull($result);
        static::assertSame('https://provider.example.test/original', $result->params->payUrl);
    }

    public function testFailedAttemptCanBeRetriedAndTheNewAttemptCanSettleTheObligation(): void
    {
        [$app, $accountId] = $this->merchant();
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $starter = $this->starter();
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->exactly(2))->method('execute')->willReturnCallback(static function (Capture $capture): void {
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/pay'));
        });
        $route = $this->route($gateway, $accountId);
        $order = $orders->create($app, new PaymentOrderRequest('retry-' . Uuid::randomHex(), 1000, 'CNY', 'Retry failed execution'));
        $starter->start($app, $order, $route);
        $first = $orders->getById($order->id);
        $orders->markFailed($first);
        $retryable = $orders->getById($order->id);
        static::assertNotNull($retryable->activeAttempt);
        static::assertSame(PaymentAttemptEntity::STATUS_FAILED, $retryable->activeAttempt->status);
        static::assertSame('opened', $retryable->stateMachineState?->getTechnicalName());

        $starter->start($app, $retryable, $route);
        $second = $orders->getById($order->id);
        static::assertNotSame($first->activeAttemptId, $second->activeAttemptId);
        $orders->markPaid($second, 1000, 'new-external-trade', ['paid' => true]);
        $paid = $orders->getById($order->id);
        static::assertNotNull($paid->paidAt);
        static::assertSame(1000, $paid->paidAmount);
        static::assertSame('paid', $paid->stateMachineState?->getTechnicalName());
        static::assertSame(PaymentAttemptEntity::STATUS_PAID, $paid->activeAttempt?->status);
        static::assertCount(2, $paid->attempts ?? []);
    }

    public function testResolvedMerchantRouteCanClaimAndPersistAnAttempt(): void
    {
        [$app, $accountId] = $this->merchant();
        $context = Context::createDefaultContext();
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        static::getContainer()->get('payment_account.repository')->update([[
            'id' => $accountId,
            'credentials' => $cipher->encrypt(['app_id' => 'resolved-merchant']),
            'productParams' => ['alipay_h5' => $cipher->encrypt(['appid' => 'resolved-application'])],
        ]], $context);
        static::getContainer()->get('payment_app_account.repository')->create([[
            'paymentAppId' => $app->id, 'accountId' => $accountId, 'enabled' => true, 'position' => 0,
        ]], $context);
        static::getContainer()->get('payment_app_channel.repository')->create([[
            'paymentAppId' => $app->id, 'channelTechnicalName' => 'alipay_h5', 'enabled' => true,
        ]], $context);
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->once())->method('execute')->willReturnCallback(static function (Capture $capture): void {
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/resolved'));
        });
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $product = new PaymentProduct('alipay_h5', 'alipay', 'Alipay H5', [PaymentInteraction::REDIRECT], 'alipay', capabilities: [PaymentCapability::CAPTURE]);
        $providers = new PaymentProviderRegistry([new PaymentProvider('alipay', 'Alipay', [], ['alipay_h5' => $product], $factory, static::createStub(PaymentNotificationExtractorInterface::class))]);
        $products = new PaymentProductRegistry($providers);
        $configs = new PaymentConfigResolver(
            $providers,
            $products,
            static::getContainer()->get('payment_app_account.repository'),
            static::getContainer()->get('payment_app_channel.repository'),
            $cipher,
            static::getContainer()->get('payment_account.repository'),
        );
        $routes = new PaymentRouteResolver($products, new PaymentProductEligibility(), $configs, [new DefaultPaymentRoutingPolicy()]);
        $route = $routes->resolveProduct($app, 'alipay_h5', new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser')));
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $order = $orders->create($app, new PaymentOrderRequest('resolved-' . Uuid::randomHex(), 1000, 'CNY', 'Resolved route'));

        $result = $this->starter()->start($app, $order, $route);

        static::assertSame('https://provider.example.test/resolved', $result->params->payUrl);
        $attempt = $orders->getById($order->id)->activeAttempt;
        static::assertNotNull($attempt);
        static::assertSame($accountId, $attempt->accountId);
        static::assertSame(PaymentAttemptEntity::STATUS_HANDOVER, $attempt->status);
        static::assertSame(['appid' => 'resolved-application'], $attempt->configuration['params']);
    }

    public function testAccountChangeAfterRoutingRejectsTheStaleClaimBeforeProviderIo(): void
    {
        [$app, $accountId] = $this->merchant();
        $gateway = static::createMock(GatewayInterface::class);
        $gateway->expects($this->never())->method('execute');
        $route = $this->route($gateway, $accountId);
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $order = $orders->create($app, new PaymentOrderRequest('stale-' . Uuid::randomHex(), 1000, 'CNY', 'Stale configuration'));
        static::getContainer()->get('payment_account.repository')->update([[
            'id' => $accountId,
            'credentials' => static::getContainer()->get(PaymentCredentialCipher::class)->encrypt(['app_id' => 'changed-after-routing']),
        ]], Context::createDefaultContext());

        try {
            $this->starter()->start($app, $order, $route);
            static::fail('A selected account must be revalidated while claiming.');
        } catch (PaymentProviderException $exception) {
            static::assertSame(PaymentProviderException::accountConfigurationChanged($accountId)->getErrorCode(), $exception->getErrorCode());
        }
        $unclaimed = $orders->getById($order->id);
        static::assertNull($unclaimed->activeAttemptId);
        static::assertCount(0, $unclaimed->attempts ?? []);
        static::assertCount(0, $unclaimed->transactions ?? []);
    }

    public function testHistoricalAccountRejectsAnotherMerchantAndRotatesOnlyItsKeys(): void
    {
        [$app, $accountId] = $this->merchant();
        $accounts = static::getContainer()->get('payment_account.repository');
        $cipher = static::getContainer()->get(PaymentCredentialCipher::class);
        $original = ['app_id' => 'original-merchant', 'sandbox' => true, 'private_key' => 'old-signing-key', 'alipay_public_key' => 'old-verification-key'];
        $accounts->update([['id' => $accountId, 'credentials' => $cipher->encrypt($original)]], Context::createDefaultContext());
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $order = $orders->create($app, new PaymentOrderRequest('identity-' . Uuid::randomHex(), 1000, 'CNY', 'Stable merchant'));
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (Capture $capture): void {
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/pay'));
        });
        $this->starter()->start($app, $order, $this->route($gateway, $accountId));
        $accounts->update([['id' => $accountId, 'productParams' => ['alipay_h5' => $cipher->encrypt(['appid' => 'next-product-application'])]]], Context::createDefaultContext());
        $rotated = array_replace($original, ['private_key' => 'new-signing-key', 'alipay_public_key' => 'new-verification-key']);
        $accounts->update([['id' => $accountId, 'name' => 'Renamed merchant', 'active' => false, 'credentials' => $cipher->encrypt($rotated)]], Context::createDefaultContext());
        $historical = static::getContainer()->get(PaymentConfigResolver::class)->resolveForOrder($app, $orders->getById($order->id));
        static::assertSame('original-merchant', $historical->credentials['app_id']);
        static::assertSame('frozen-application', $historical->params['appid']);
        static::assertTrue($historical->credentials['sandbox']);
        static::assertSame('new-signing-key', $historical->credentials['private_key']);
        static::assertSame('new-verification-key', $historical->credentials['alipay_public_key']);
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($accountId));

        $accounts->update([['id' => $accountId, 'credentials' => $cipher->encrypt(array_replace($rotated, ['app_id' => 'another-merchant']))]], Context::createDefaultContext());
    }

    public function testAccountIdentityCannotBeChangedThroughTheAdminCrudApiScope(): void
    {
        [, $accountId] = $this->merchant();
        $this->expectException(WriteException::class);
        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, static function (Context $context) use ($accountId): void {
            static::getContainer()->get('payment_account.repository')->update([['id' => $accountId, 'providerTechnicalName' => 'another-provider']], $context);
        });
    }

    public function testAttemptRouteCannotBeRewrittenThroughTheAdminCrudApiScope(): void
    {
        [$app, $accountId] = $this->merchant();
        $gateway = static::createStub(GatewayInterface::class);
        $gateway->method('execute')->willReturnCallback(static function (Capture $capture): void {
            $capture->getModel()[PaymentGatewayModel::RESULT] = new PaymentStartResult(new PaymentHandover(PaymentInteraction::REDIRECT, 'https://provider.example.test/original'));
        });
        $route = $this->route($gateway, $accountId);
        $orders = static::getContainer()->get(PaymentOrderService::class);
        $order = $orders->create($app, new PaymentOrderRequest('obligation-' . Uuid::randomHex(), 1000, 'CNY', 'Protected execution'));
        $this->starter()->start($app, $order, $route);
        $attempt = $orders->getById($order->id)->activeAttempt;
        static::assertNotNull($attempt);
        $context = Context::createDefaultContext();

        $this->expectException(WriteException::class);
        $context->scope(Context::CRUD_API_SCOPE, static function (Context $context) use ($attempt): void {
            static::getContainer()->get('payment_attempt.repository')->update([['id' => $attempt->id, 'channelTechnicalName' => 'wechat_native']], $context);
        });
    }

    private function starter(): PaymentOrderStarter
    {
        return new PaymentOrderStarter(
            static::getContainer()->get('payment_transaction.repository'),
            static::getContainer()->get('payment_attempt.repository'),
            static::getContainer()->get('payment_order.repository'),
            static::getContainer()->get(PaymentOrderService::class),
            static::getContainer()->get(PaymentNotifyUrlBuilder::class),
            static::getContainer()->get(AbstractNumberRangeValueGenerator::class),
            static::getContainer()->get(PaymentOrderRowLock::class),
            static::getContainer()->get(PaymentAccountLock::class),
        );
    }

    /**
     * @return array{PaymentAppEntity, string}
     */
    private function merchant(): array
    {
        $appId = Uuid::randomHex();
        $accountId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        /** @var EntityRepository<PaymentAppCollection> $apps */
        $apps = static::getContainer()->get('payment_app.repository');
        $apps->create([[
            'id' => $appId,
            'technicalName' => 'attempt_test_' . Uuid::randomHex(),
            'name' => 'Attempt persistence merchant',
            'type' => PaymentAppEntity::TYPE_EXTERNAL,
            'active' => true,
            'appSecret' => static::getContainer()->get(PaymentCredentialCipher::class)->encryptValue('secret'),
        ]], $context);
        /** @var EntityRepository<PaymentAccountCollection> $accounts */
        $accounts = static::getContainer()->get('payment_account.repository');
        $accounts->create([[
            'id' => $accountId,
            'providerTechnicalName' => 'alipay',
            'name' => 'Original merchant',
            'enabledProducts' => ['alipay_h5'],
            'ownerAppId' => $appId,
            'active' => true,
        ]], $context);
        $app = $apps->search(new Criteria([$appId]), $context)->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);

        return [$app, $accountId];
    }

    private function route(GatewayInterface $gateway, string $accountId): PaymentRoute
    {
        $factory = static::createStub(PaymentGatewayFactory::class);
        $factory->method('createForPayment')->willReturn($gateway);
        $product = new PaymentProduct(technicalName: 'alipay_h5', providerTechnicalName: 'alipay', label: 'Alipay H5', interactions: [PaymentInteraction::REDIRECT], method: 'alipay');
        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: 'Alipay',
            credentials: [],
            products: ['alipay_h5' => $product],
            gatewayFactory: $factory,
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        return new PaymentRoute(
            new ResolvedPaymentConfig($provider, $product, [], ['appid' => 'frozen-application'], [], [], $accountId, accountFingerprint: PaymentAccountLock::fingerprint(static::getContainer()->get(PaymentConfigResolver::class)->loadAccount($accountId))),
            new PaymentContext('CNY', new PaymentScene('browser', 'web', 'browser')),
            PaymentInteraction::REDIRECT,
        );
    }
}
