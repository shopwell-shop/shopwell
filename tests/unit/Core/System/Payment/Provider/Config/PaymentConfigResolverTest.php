<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppAccount\PaymentAppAccountEntity;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelCollection;
use Shopwell\Core\System\Payment\App\Aggregate\PaymentAppChannel\PaymentAppChannelEntity;
use Shopwell\Core\System\Payment\App\PaymentAppEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Order\Aggregate\PaymentAttempt\PaymentAttemptEntity;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\Config\PaymentChannelConfigEntity;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentConfigResolver::class)]
class PaymentConfigResolverTest extends TestCase
{
    private PaymentCredentialCipher $cipher;

    private PaymentAppEntity $app;

    private PaymentProduct $product;

    private PaymentAccountEntity $account;

    private PaymentAppAccountEntity $grant;

    private PaymentAppChannelEntity $assignment;

    private PaymentChannelConfigEntity $platform;

    protected function setUp(): void
    {
        $this->cipher = new PaymentCredentialCipher('test-secret');
        $this->app = new PaymentAppEntity();
        $this->app->id = 'app';
        $this->app->setUniqueIdentifier($this->app->id);
        $this->app->technicalName = 'client';
        $this->product = new PaymentProduct('stripe_checkout', 'stripe', 'Stripe', [PaymentInteraction::REDIRECT], 'stripe', ['CNY'], [new PaymentScene('browser', 'web')]);
        $this->account = new PaymentAccountEntity();
        $this->account->id = 'merchant-account';
        $this->account->setUniqueIdentifier($this->account->id);
        $this->account->providerTechnicalName = 'stripe';
        $this->account->name = 'Original merchant';
        $this->account->active = true;
        $this->account->enabledProducts = ['stripe_checkout'];
        $this->account->credentials = $this->cipher->encrypt(['secret_key' => 'merchant-secret']);
        $this->account->productParams = ['stripe_checkout' => $this->cipher->encrypt(['app_id' => 'merchant-app'])];
        $this->grant = new PaymentAppAccountEntity();
        $this->grant->id = 'grant';
        $this->grant->setUniqueIdentifier($this->grant->id);
        $this->grant->paymentAppId = $this->app->id;
        $this->grant->accountId = $this->account->id;
        $this->grant->enabled = true;
        $this->grant->position = 0;
        $this->grant->account = $this->account;
        $this->assignment = new PaymentAppChannelEntity();
        $this->assignment->id = 'product-assignment';
        $this->assignment->setUniqueIdentifier($this->assignment->id);
        $this->assignment->paymentAppId = $this->app->id;
        $this->assignment->channelTechnicalName = 'stripe_checkout';
        $this->assignment->enabled = true;
        $this->platform = new PaymentChannelConfigEntity();
        $this->platform->id = 'platform-product';
        $this->platform->setUniqueIdentifier($this->platform->id);
        $this->platform->providerTechnicalName = 'stripe';
        $this->platform->channelTechnicalName = 'stripe_checkout';
        $this->platform->enabled = true;
    }

    public function testUnknownProductIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::unknownChannel('missing'));
        $this->resolver()->resolve($this->app, 'missing', 'CNY');
    }

    public function testUnsupportedCurrencyIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::currencyNotSupported('stripe_checkout', 'USD'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'USD');
    }

    public function testUnsupportedDeviceIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::deviceNotSupported('stripe_checkout', 'app'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY', 'app');
    }

    public function testRequiredRuntimeParametersAreValidated(): void
    {
        $this->product = new PaymentProduct('stripe_checkout', 'stripe', 'Stripe', [PaymentInteraction::REDIRECT], 'stripe', runtimeParams: ['openid' => true]);
        $this->expectExceptionObject(PaymentProviderException::accountSelectionRequired('stripe_checkout'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY');
    }

    public function testPayerIdentityCannotCrossMerchantAccountsOfTheSameProduct(): void
    {
        $this->product = new PaymentProduct('stripe_checkout', 'stripe', 'Stripe', [PaymentInteraction::REDIRECT], 'stripe', runtimeParams: ['openid' => true]);
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null, ['openid' => 'common-payer']));
        $resolved = $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null, ['openid' => 'wrong-payer'], ['merchant-account' => ['stripe_checkout' => ['openid' => 'merchant-payer']]]);
        static::assertCount(1, $resolved);
        static::assertSame('merchant-payer', $resolved[0]->runtimeParams['openid']);
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null, [], ['other-account' => ['stripe_checkout' => ['openid' => 'other-payer']]]));
    }

    public function testExplicitAccountRequestUsesOnlyItsSuppliedPayerIdentity(): void
    {
        $this->product = new PaymentProduct('stripe_checkout', 'stripe', 'Stripe', [PaymentInteraction::REDIRECT], 'stripe', runtimeParams: ['openid' => true]);
        $resolved = $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY', runtimeParams: ['openid' => 'explicit-payer'], accountId: $this->account->id);
        static::assertSame('explicit-payer', $resolved->runtimeParams['openid']);
    }

    public function testDisabledProductAssignmentIsRejected(): void
    {
        $this->assignment->enabled = false;
        $this->expectExceptionObject(PaymentProviderException::channelNotEnabled('stripe_checkout', 'client'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY');
    }

    public function testAccountProductAdmissionIsRequired(): void
    {
        $this->account->enabledProducts = [];
        $this->expectExceptionObject(PaymentProviderException::accountSelectionRequired('stripe_checkout'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY');
    }

    public function testDisabledMerchantAccountIsNotACandidate(): void
    {
        $this->account->active = false;
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null));
    }

    public function testAnAccountOfAnotherProviderCannotBeUsed(): void
    {
        $this->account->providerTechnicalName = 'paypal';
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null));
    }

    public function testAnUnconfiguredAccountDoesNotBecomeACandidate(): void
    {
        $this->account->credentials = null;
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null));
    }

    public function testMerchantCredentialsAndProductParametersComeFromTheSelectedAccount(): void
    {
        $resolved = $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY', runtimeParams: ['payer' => 'customer']);
        static::assertSame('merchant-account', $resolved->accountId);
        static::assertSame(['secret_key' => 'merchant-secret'], $resolved->credentials);
        static::assertSame(['app_id' => 'merchant-app'], $resolved->params);
        static::assertSame(['payer' => 'customer'], $resolved->runtimeParams);
    }

    public function testSettingsApplyAccountDefaultsThenGrantThenAppProduct(): void
    {
        $this->account->settings = ['base' => 'account', 'winner' => 'account'];
        $this->platform->settings = ['platform' => 'platform', 'winner' => 'platform'];
        $this->grant->settings = ['grant' => 'grant', 'winner' => 'grant'];
        $this->assignment->settings = ['winner' => 'product'];
        static::assertSame(['base' => 'account', 'winner' => 'product', 'grant' => 'grant'], $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY')->settings);
    }

    public function testAnExplicitUnauthorizedAccountIsRejected(): void
    {
        $this->expectExceptionObject(PaymentProviderException::accountNotAuthorized('other-account', 'client'));
        $this->resolver()->resolve($this->app, 'stripe_checkout', 'CNY', accountId: 'other-account');
    }

    public function testHistoricalAttemptUsesFrozenParametersAndRotatedCredentialsWhileAdmissionIsDisabled(): void
    {
        $this->account->active = false;
        $this->account->credentials = $this->cipher->encrypt(['secret_key' => 'rotated-key']);
        $this->platform->enabled = false;
        $this->assignment->enabled = false;
        $order = new PaymentOrderEntity();
        $order->id = 'order';
        $order->paymentAppId = $this->app->id;
        $attempt = new PaymentAttemptEntity();
        $attempt->paymentOrderId = $order->id;
        $attempt->accountId = $this->account->id;
        $attempt->providerTechnicalName = 'stripe';
        $attempt->channelTechnicalName = 'stripe_checkout';
        $attempt->configuration = ['params' => ['app_id' => 'frozen-app'], 'runtimeParams' => ['payer' => 'original'], 'settings' => ['frozen' => true]];
        $order->activeAttempt = $attempt;
        $resolved = $this->resolver()->resolveForOrder($this->app, $order);
        static::assertSame(['secret_key' => 'rotated-key'], $resolved->credentials);
        static::assertSame(['app_id' => 'frozen-app'], $resolved->params);
        static::assertSame(['payer' => 'original'], $resolved->runtimeParams);
        static::assertSame(['frozen' => true], $resolved->settings);
    }

    public function testHistoricalAccountWithMissingKeysFailsClosed(): void
    {
        $this->account->credentials = null;
        $this->expectExceptionObject(PaymentProviderException::providerNotConfigured('stripe'));
        $this->resolver()->accountCredentials($this->account->id, 'stripe');
    }

    public function testRevokedAccountGrantCannotAdmitCandidates(): void
    {
        $this->grant->enabled = false;
        static::assertSame([], $this->resolver()->resolveCandidates($this->app, 'stripe_checkout', 'CNY', null));
        static::assertSame([], $this->resolver()->enabledFor($this->app));
    }

    public function testAppProductListIntersectsAccountEnabledProducts(): void
    {
        $this->account->enabledProducts = [];
        static::assertSame([], $this->resolver()->enabledFor($this->app));
    }

    public function testEnabledProductsContainOnlyAuthorizedProviderProducts(): void
    {
        $enabled = $this->resolver()->enabledFor($this->app);
        static::assertCount(1, $enabled);
        static::assertSame('stripe_checkout', $enabled[0]['channel']);
    }

    private function resolver(): PaymentConfigResolver
    {
        $providers = new PaymentProviderRegistry([new PaymentProvider('stripe', 'Stripe', [], ['stripe_checkout' => $this->product], new PaymentGatewayFactory('stripe', AbstractPaymentApi::class, []), static::createStub(PaymentNotificationExtractorInterface::class))]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentProductRegistry($providers),
            new StaticEntityRepository([new PaymentAppAccountCollection([$this->grant])]),
            new StaticEntityRepository([new PaymentAppChannelCollection([$this->assignment])]),
            $this->cipher,
            new StaticEntityRepository([new PaymentAccountCollection([$this->account])]),
        );
    }
}
