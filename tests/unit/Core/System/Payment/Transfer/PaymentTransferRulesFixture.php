<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Transfer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Integration\Alipay\AlipayProvider;
use Shopwell\Core\System\Payment\Integration\Paypal\PaypalProvider;
use Shopwell\Core\System\Payment\Integration\Stripe\StripeProvider;
use Shopwell\Core\System\Payment\Integration\Wechat\WechatProvider;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\AbstractPaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;
use Shopwell\Core\System\Payment\Provider\Transfer\PaymentTransferRules;

/**
 * The registry the payout services read their rules from, built from the real declarations.
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
    public static function registry(): PaymentProviderRegistry
    {
        return self::registryOf(self::declarations());
    }

    /**
     * The payout rules of one built-in interface, or null when the name is not one of them. Tests that
     * build their own provider snapshots attach these instead of inventing a second matrix.
     */
    public static function rulesOf(string $technicalName): ?PaymentTransferRules
    {
        foreach (self::declarations() as $declaration) {
            if ($declaration->getName() === $technicalName) {
                return $declaration->getTransferRules();
            }
        }

        return null;
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
            channels: [],
            gatewayFactory: new PaymentGatewayFactory($name, AbstractPaymentApi::class, []),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
            transferRules: $declaration->getTransferRules(),
        );
    }

    /**
     * The declarations of the built-in interfaces, read without their constructor: that constructor
     * wants the ten actions of the interface, but the payout rules are a pure function of the class -
     * they are what this fixture exists for, and building a whole gateway for them would be noise.
     *
     * @return list<AbstractPaymentProvider>
     */
    private static function declarations(): array
    {
        return array_map(static function (string $class): AbstractPaymentProvider {
            $declaration = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            \assert($declaration instanceof AbstractPaymentProvider);

            return $declaration;
        }, [AlipayProvider::class, WechatProvider::class, StripeProvider::class, PaypalProvider::class]);
    }
}
