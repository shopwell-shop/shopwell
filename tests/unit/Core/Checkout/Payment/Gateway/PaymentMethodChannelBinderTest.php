<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\Payment\Gateway;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelCollection;
use Shopwell\Core\Checkout\Payment\Aggregate\PaymentMethodGatewayChannel\PaymentMethodGatewayChannelEntity;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentGatewayHandler;
use Shopwell\Core\Checkout\Payment\Gateway\PaymentMethodChannelBinder;
use Shopwell\Core\Checkout\Payment\PaymentException;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Payment\PaymentMethodEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\App\PaymentAppCollection;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\Exception\PaymentAppException;
use Shopwell\Core\System\Payment\Exception\PaymentCheckoutBindingException;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Method\PaymentMethod;
use Shopwell\Core\System\Payment\Method\PaymentMethodRegistry;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Api\PaymentControllerFixture;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentMethodChannelBinder::class)]
class PaymentMethodChannelBinderTest extends TestCase
{
    /**
     * @var StaticEntityRepository<PaymentMethodGatewayChannelCollection>
     */
    private StaticEntityRepository $bindings;

    /**
     * @var StaticEntityRepository<PaymentMethodCollection>
     */
    private StaticEntityRepository $methods;

    /**
     * @var StaticEntityRepository<PaymentAppCollection>
     */
    private StaticEntityRepository $apps;

    private PaymentMethodChannelBinder $binder;

    protected function setUp(): void
    {
        $method = (new PaymentMethodEntity())->assign(['id' => 'checkout-method', 'handlerIdentifier' => 'invoice']);
        $app = (new PaymentAppEntity())->assign(['id' => 'app', 'active' => true, 'technicalName' => 'platform']);
        $app->setUniqueIdentifier($app->id);
        $this->bindings = StaticEntityRepository::of(PaymentMethodGatewayChannelCollection::class);
        $this->methods = new StaticEntityRepository([new PaymentMethodCollection([$method])]);
        $this->apps = new StaticEntityRepository([new PaymentAppCollection([$app])]);
        $this->binder = new PaymentMethodChannelBinder($this->bindings, $this->methods, $this->apps, new PaymentMethodRegistry([new PaymentMethod('plugin_method', 'Plugin')]), PaymentGatewayFixture::channelRegistry());
    }

    public function testBindingSavesGlobalMethodAndPolicyAndSelectsGatewayHandler(): void
    {
        $this->bindings->addSearch(new PaymentMethodGatewayChannelCollection(), new PaymentMethodGatewayChannelCollection());

        $binding = $this->binder->bind('checkout-method', 'plugin_method', null, Context::createDefaultContext(), 'merchant_priority');

        static::assertSame('plugin_method', $binding->methodTechnicalName);
        static::assertSame('merchant_priority', $binding->routingPolicy);
        static::assertSame('app', $binding->paymentAppId);
        static::assertSame([['id' => $binding->id, 'paymentMethodId' => 'checkout-method', 'paymentAppId' => 'app', 'methodTechnicalName' => 'plugin_method', 'routingPolicy' => 'merchant_priority']], $this->bindings->upserts[0]);
        static::assertSame(PaymentGatewayHandler::class, $this->methods->updates[0][0]['handlerIdentifier']);
    }

    #[DataProvider('selectableMethodProvider')]
    public function testSelectableMethodsRequireAuthorizedBrowserProducts(bool $grantEnabled, bool $accountActive, bool $productEnabled, string $interaction, bool $knownProduct, bool $expected): void
    {
        $app = PaymentControllerFixture::app('app', 'private-secret');
        $accountGrant = PaymentControllerFixture::appAccount($app->id, $grantEnabled);
        static::assertNotNull($accountGrant->account);
        $accountGrant->account->active = $accountActive;
        $app->accounts = [$accountGrant->accountId => $accountGrant];
        $productGrant = PaymentControllerFixture::appChannel($app->id, $productEnabled);
        if (!$knownProduct) {
            $productGrant->channelTechnicalName = 'removed-product';
        }
        $app->channels = [$productGrant->channelTechnicalName => $productGrant];
        $this->apps->searches = [new PaymentAppCollection([$app])];
        $binder = new PaymentMethodChannelBinder($this->bindings, $this->methods, $this->apps, new PaymentMethodRegistry([new PaymentMethod('alipay', 'payment.alipay')]), PaymentGatewayFixture::channelRegistry($interaction));
        $apps = $binder->selectableApps(Context::createDefaultContext());
        static::assertSame($expected ? [['technicalName' => 'alipay', 'translationKey' => 'payment.alipay']] : [], $apps[0]['methods']);
    }

    /**
     * @return iterable<string, array{bool, bool, bool, string, bool, bool}>
     */
    public static function selectableMethodProvider(): iterable
    {
        yield 'authorized redirect' => [true, true, true, PaymentInteraction::REDIRECT, true, true];
        yield 'authorized QR' => [true, true, true, PaymentInteraction::QRCODE, true, true];
        yield 'revoked account grant' => [false, true, true, PaymentInteraction::REDIRECT, true, false];
        yield 'inactive account' => [true, false, true, PaymentInteraction::REDIRECT, true, false];
        yield 'disabled product grant' => [true, true, false, PaymentInteraction::REDIRECT, true, false];
        yield 'SDK only' => [true, true, true, PaymentInteraction::SDK, true, false];
        yield 'unknown product' => [true, true, true, PaymentInteraction::REDIRECT, false, false];
    }

    public function testAccountProductAdmissionIsRequiredForCheckoutSelection(): void
    {
        $app = PaymentControllerFixture::app('app', 'secret');
        $grant = PaymentControllerFixture::appAccount($app->id);
        static::assertNotNull($grant->account);
        $grant->account->enabledProducts = [];
        $app->accounts = [$grant->accountId => $grant];
        $app->channels = ['alipay_h5' => PaymentControllerFixture::appChannel($app->id)];
        $this->apps->searches = [new PaymentAppCollection([$app])];
        $binder = new PaymentMethodChannelBinder($this->bindings, $this->methods, $this->apps, new PaymentMethodRegistry([new PaymentMethod('alipay', 'payment.alipay')]), PaymentGatewayFixture::channelRegistry());
        static::assertSame([], $binder->selectableApps(Context::createDefaultContext())[0]['methods']);
    }

    public function testSelectableMethodsDeduplicateProductsOfTheSameLogicalMethod(): void
    {
        $app = PaymentControllerFixture::app('app', 'secret');
        $grant = PaymentControllerFixture::appAccount($app->id);
        $grant->enabled = true;
        static::assertNotNull($grant->account);
        $grant->account->enabledProducts = ['alipay_h5', 'alipay_qr'];
        $app->accounts = [$grant->accountId => $grant];
        $h5 = PaymentControllerFixture::appChannel($app->id);
        $qr = clone $h5;
        $qr->channelTechnicalName = 'alipay_qr';
        $app->channels = ['alipay_h5' => $h5, 'alipay_qr' => $qr];
        $this->apps->searches = [new PaymentAppCollection([$app])];
        $products = new PaymentProductRegistry(PaymentControllerFixture::providerRegistry(PaymentControllerFixture::provider('alipay', [PaymentGatewayFixture::channel(), new PaymentProduct('alipay_qr', 'alipay', 'QR', [PaymentInteraction::QRCODE], 'alipay')])));
        $binder = new PaymentMethodChannelBinder($this->bindings, $this->methods, $this->apps, new PaymentMethodRegistry([new PaymentMethod('alipay', 'payment.alipay')]), $products);
        static::assertSame([['technicalName' => 'alipay', 'translationKey' => 'payment.alipay']], $binder->selectableApps(Context::createDefaultContext())[0]['methods']);
    }

    public function testAnotherCheckoutMethodCannotClaimTheSameAppAndLogicalMethod(): void
    {
        $taken = new PaymentMethodGatewayChannelEntity();
        $taken->id = 'taken-binding';
        $taken->setUniqueIdentifier($taken->id);
        $taken->paymentMethodId = 'other-checkout-method';
        $this->bindings->searches = [new PaymentMethodGatewayChannelCollection([$taken])];
        $otherMethod = (new PaymentMethodEntity())->assign(['id' => 'other-checkout-method', 'name' => 'Other checkout method']);
        $this->methods->addSearch(new PaymentMethodCollection([$otherMethod]));
        $this->expectExceptionObject(PaymentCheckoutBindingException::paymentMethodChannelTaken('platform', 'plugin_method', 'other-checkout-method', 'Other checkout method'));
        try {
            $this->binder->bind('checkout-method', 'plugin_method', 'app', Context::createDefaultContext());
        } finally {
            static::assertSame([], $this->bindings->upserts);
            static::assertSame([], $this->methods->updates);
        }
    }

    public function testUnknownMethodIsRejectedBeforeAnyWrite(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownMethod('missing'));
        try {
            $this->binder->bind('checkout-method', 'missing', null, Context::createDefaultContext());
        } finally {
            static::assertSame([], $this->bindings->upserts);
            static::assertSame([], $this->methods->updates);
        }
    }

    public function testUnknownCheckoutMethodIsRejectedBeforeAnyWrite(): void
    {
        $this->methods->searches = [new PaymentMethodCollection()];
        $this->expectExceptionObject(PaymentException::unknownPaymentMethodById('missing'));
        $this->binder->bind('missing', 'plugin_method', null, Context::createDefaultContext());
    }

    public function testMissingAppIsRejected(): void
    {
        $this->apps->searches = [new PaymentAppCollection()];
        $this->expectExceptionObject(PaymentAppException::appNotFound('missing'));
        $this->binder->bind('checkout-method', 'plugin_method', 'missing', Context::createDefaultContext());
    }

    public function testDisabledAppIsRejected(): void
    {
        $app = (new PaymentAppEntity())->assign(['id' => 'app', 'active' => false, 'technicalName' => 'disabled']);
        $app->setUniqueIdentifier($app->id);
        $this->apps->searches = [new PaymentAppCollection([$app])];
        $this->expectExceptionObject(PaymentAppException::appDisabled('disabled'));
        $this->binder->bind('checkout-method', 'plugin_method', 'app', Context::createDefaultContext());
    }
}
