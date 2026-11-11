<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Account\PaymentAccountCollection;
use Shopwell\Core\System\Payment\Account\PaymentAccountEntity;
use Shopwell\Core\System\Payment\App\PaymentCredentialCipher;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentProductRegistry;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigResolver;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\System\Payment\Provider\Fixtures\PlatformProviders;

/**
 * A payout registry with built-in declarations and protocol-independent external-provider fixtures.
 *
 * A test that books a payout has to validate against the very matrix production uses. Hand written
 * lists of identity types, required fields and currencies in the tests would be a second truth that
 * silently diverges from the declarations - and a declaration that changes its rules would leave the
 * suite green while production refuses the payout.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
final class PaymentTransferRulesFixture extends TestCase
{
    public static function resolvedConfig(?PaymentProviderRegistry $providers = null): PaymentConfigResolver
    {
        $resolver = static::createStub(PaymentConfigResolver::class);
        $resolver->method('resolve')->willReturnCallback(static function ($app, string $productName, string $currency, $device = null, array $runtimeParams = [], ?string $accountId = null) use ($providers): ResolvedPaymentConfig {
            foreach ($providers?->all() ?? [] as $provider) {
                if (isset($provider->products[$productName])) {
                    return new ResolvedPaymentConfig($provider, $provider->products[$productName], [], [], $runtimeParams, [], $accountId ?? 'account-id');
                }
            }
            foreach (self::registry()->all() as $provider) {
                if (isset($provider->products[$productName])) {
                    return new ResolvedPaymentConfig($provider, $provider->products[$productName], [], [], $runtimeParams, [], $accountId ?? 'account-id');
                }
            }
            throw new \LogicException('Unknown payment product ' . $productName);
        });

        return $resolver;
    }

    public static function boundResolver(PaymentProvider $provider, PaymentCredentialCipher $cipher): PaymentConfigResolver
    {
        $account = new PaymentAccountEntity();
        $account->id = 'account-id';
        $account->setUniqueIdentifier($account->id);
        $account->name = 'Original account';
        $account->providerTechnicalName = $provider->technicalName;
        $account->active = true;
        $account->enabledProducts = array_keys($provider->products);
        $account->credentials = $cipher->encrypt(['app_id' => 'own-app-id', 'alipay_public_key' => 'key']);
        $account->productParams = ['alipay_h5' => $cipher->encrypt(['appid' => 'own-appid'])];
        $providers = new PaymentProviderRegistry([$provider]);

        return new PaymentConfigResolver(
            $providers,
            new PaymentProductRegistry($providers),
            new StaticEntityRepository([]),
            new StaticEntityRepository([]),
            $cipher,
            new StaticEntityRepository([new PaymentAccountCollection([$account])]),
        );
    }

    public static function registry(): PaymentProviderRegistry
    {
        return new PaymentProviderRegistry([
            self::provider(PlatformProviders::alipay()),
            self::provider(PlatformProviders::wechat()),
            self::externalProvider('stripe', ['stripe_account'], true),
            self::externalProvider('paypal', ['paypal_email', 'paypal_phone', 'paypal_payer_id'], false),
        ]);
    }

    /**
     * The payout rules of one built-in interface, or null when the name is not one of them. Tests that
     * build their own provider snapshots attach these instead of inventing a second matrix.
     */
    public static function rulesOf(string $technicalName): ?PaymentTransferRules
    {
        $registry = self::registry();

        return $registry->has($technicalName) ? $registry->get($technicalName)->transferRules : null;
    }

    /**
     * @param list<AbstractPaymentProvider> $declarations
     */
    public static function registryOf(array $declarations): PaymentProviderRegistry
    {
        $providers = [];

        foreach ($declarations as $declaration) {
            $providers[] = self::provider($declaration);
        }

        return new PaymentProviderRegistry($providers);
    }

    /**
     * A snapshot reduced to what the payout services read: the rules of the declaration.
     */
    public static function provider(AbstractPaymentProvider $declaration): PaymentProvider
    {
        $name = $declaration->getName();

        return new PaymentProvider(
            technicalName: $name,
            label: $name,
            credentials: [],
            products: array_column($declaration->getProducts(), null, 'technicalName'),
            gatewayFactory: new PaymentGatewayFactory($name, AbstractPaymentApi::class, []),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            transferRules: $declaration->getTransferRules(),
        );
    }

    /**
     * External protocols are test data here; their actual declarations are covered by their plugin.
     *
     * @param list<string> $identities
     */
    private static function externalProvider(string $name, array $identities, bool $onboarding): PaymentProvider
    {
        $product = new PaymentProduct($name . '_checkout', $name, $name, [PaymentInteraction::REDIRECT], $name);

        return new PaymentProvider(
            technicalName: $name,
            label: $name,
            credentials: [],
            products: [$product->technicalName => $product],
            gatewayFactory: new PaymentGatewayFactory($name, AbstractPaymentApi::class, []),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            transferRules: new PaymentTransferRules(identityTypes: $identities, requiresOnboarding: $onboarding),
        );
    }
}
