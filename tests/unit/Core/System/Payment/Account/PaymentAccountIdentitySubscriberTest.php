<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\Account\PaymentAccountIdentitySubscriber;
use Shopwell\Core\System\Payment\Account\PaymentAccountLock;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Exception\PaymentProviderException;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentAccountIdentitySubscriber::class)]
class PaymentAccountIdentitySubscriberTest extends TestCase
{
    #[DataProvider('durableBindings')]
    public function testHistoricalBindingsPreventMerchantAndEnvironmentChanges(string $binding): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->credentials = $this->cipher()->encrypt(['merchant' => 'different-merchant', 'environment' => 'sandbox', 'signing_key' => 'original-key']);
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($current->id));

        $this->subscriber($binding)->validateIdentity($current, $next);
    }

    public static function durableBindings(): \Generator
    {
        yield 'payment attempt' => ['attempt'];
        yield 'payout' => ['transfer'];
        yield 'subscription' => ['subscription'];
        yield 'mandate' => ['mandate'];
    }

    public function testPublicProductIdentityCannotChangeOnAHistoricalAccount(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->productParams = ['plugin' => $this->cipher()->encrypt(['application' => 'other-application'])];
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($current->id));

        $this->subscriber('transfer')->validateIdentity($current, $next);
    }

    public function testEstablishedAccountCanConfigureAnotherProduct(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->productParams['another-product'] = $this->cipher()->encrypt(['application' => 'new-application']);
        $this->subscriber('transfer')->validateIdentity($current, $next);

        static::assertNotNull($next->productParams);
        static::assertArrayHasKey('another-product', $next->productParams);
    }

    public function testCollectionHistoryUsesItsFrozenProductParamsAndDoesNotLockTheAccountProduct(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->productParams = ['plugin' => $this->cipher()->encrypt(['application' => 'next-application'])];
        $this->subscriber('attempt')->validateIdentity($current, $next);

        static::assertNotSame($current->productParams, $next->productParams);
    }

    public function testExecutionSettingsCannotSwitchEnvironmentAfterBinding(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->settings = ['endpoint' => 'production'];
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($current->id));

        $this->subscriber('subscription')->validateIdentity($current, $next);
    }

    public function testOwnerCannotBeReassignedAfterBinding(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->ownerAppId = 'another-app';
        $this->expectExceptionObject(PaymentProviderException::accountIdentityImmutable($current->id));

        $this->subscriber('attempt')->validateIdentity($current, $next);
    }

    public function testDeclaredSigningAndVerificationKeysRotateWithoutChangingIdentity(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->name = 'Renamed account';
        $next->active = false;
        $next->credentials = $this->cipher()->encrypt(['environment' => 'sandbox', 'merchant' => 'original-merchant', 'signing_key' => 'new-key', 'verification_certificate' => 'new-public-certificate']);
        $next->productParams = ['plugin' => $this->cipher()->encrypt(['application' => 'original-application', 'product_secret' => 'new-product-secret'])];
        $this->subscriber('attempt')->validateIdentity($current, $next);

        static::assertNotSame($current->credentials, $next->credentials);
    }

    public function testUnusedAccountCanStillBeConfigured(): void
    {
        $current = $this->account();
        $next = clone $current;
        $next->credentials = $this->cipher()->encrypt(['merchant' => 'corrected-merchant', 'environment' => 'live']);
        $this->subscriber(null)->validateIdentity($current, $next);

        static::assertNotSame($current->credentials, $next->credentials);
    }

    private function subscriber(?string $binding): PaymentAccountIdentitySubscriber
    {
        $product = new PaymentProduct('plugin', 'plugin', 'Plugin', ['redirect'], 'wallet', params: [new PaymentConfigField('product_secret', secret: true)]);
        $provider = new PaymentProvider('plugin', 'Plugin', [new PaymentConfigField('merchant'), new PaymentConfigField('environment'), new PaymentConfigField('signing_key', secret: true), new PaymentConfigField('verification_certificate', secret: true)], ['plugin' => $product], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class));

        $lock = static::createStub(PaymentAccountLock::class);
        $lock->method('hasBinding')->willReturnCallback(static fn (string $table, string $accountId, ?string $product = null): bool => $table === 'payment_' . $binding && ($product === null || $product === 'plugin'));

        return new PaymentAccountIdentitySubscriber($lock, new PaymentProviderRegistry([$provider]), $this->cipher());
    }

    private function account(): PaymentAccountEntity
    {
        $account = new PaymentAccountEntity();
        $account->id = 'account-id';
        $account->providerTechnicalName = 'plugin';
        $account->ownerAppId = 'owner-app';
        $account->name = 'Original merchant';
        $account->active = true;
        $account->credentials = $this->cipher()->encrypt(['merchant' => 'original-merchant', 'environment' => 'sandbox', 'signing_key' => 'original-key', 'verification_certificate' => 'original-public-certificate']);
        $account->productParams = ['plugin' => $this->cipher()->encrypt(['application' => 'original-application', 'product_secret' => 'original-product-secret'])];
        $account->settings = ['endpoint' => 'sandbox'];

        return $account;
    }

    private function cipher(): PaymentCredentialCipher
    {
        return new PaymentCredentialCipher('identity-test-secret');
    }
}
