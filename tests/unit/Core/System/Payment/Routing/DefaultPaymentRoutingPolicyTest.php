<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Product\PaymentScene;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\ResolvedPaymentConfig;
use Shopwell\Core\System\Payment\Routing\DefaultPaymentRoutingPolicy;
use Shopwell\Core\System\Payment\Routing\PaymentContext;
use Shopwell\Core\System\Payment\Routing\PaymentRoute;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefaultPaymentRoutingPolicy::class)]
class DefaultPaymentRoutingPolicyTest extends TestCase
{
    public function testItUsesFirstEligibleProductOfAnExtension(): void
    {
        $strategy = new DefaultPaymentRoutingPolicy();
        $context = new PaymentContext('EUR', new PaymentScene('browser', 'web', 'browser'));
        $channel = new PaymentProduct('plugin_checkout', 'plugin', 'Plugin', [PaymentInteraction::REDIRECT], 'plugin');
        $config = new ResolvedPaymentConfig(
            new PaymentProvider('plugin', 'plugin', [], [], static::createStub(PaymentGatewayFactory::class), static::createStub(PaymentNotificationExtractorInterface::class)),
            $channel,
            [],
            [],
            [],
            [],
            'account',
        );
        $route = new PaymentRoute($config, $context, PaymentInteraction::REDIRECT);
        static::assertSame('priority', $strategy->getName());
        static::assertSame($route, $strategy->choose([$route], $context));
        $preferred = new PaymentRoute(new ResolvedPaymentConfig($config->provider, $channel, [], [], [], [], 'preferred-account', -10), $context, PaymentInteraction::REDIRECT);
        static::assertSame($preferred, $strategy->choose([$route, $preferred], $context));
        static::assertSame($preferred, $strategy->choose([$preferred, $route], $context));
    }
}
