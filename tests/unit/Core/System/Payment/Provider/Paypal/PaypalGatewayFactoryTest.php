<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Paypal;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Gateway;
use Payum\Core\Request\Cancel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Provider\Paypal\PaypalGatewayFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaypalGatewayFactory::class)]
class PaypalGatewayFactoryTest extends TestCase
{
    public function testProviderReturnsTheTechnicalName(): void
    {
        static::assertSame('paypal', (new PaypalGatewayFactory())->provider());
    }

    public function testCreateConfigFillsTheProviderDefaults(): void
    {
        $config = (new PaypalGatewayFactory())->createConfig();

        static::assertSame('paypal', $config['payum.factory_name']);
        static::assertSame('paypal', $config['payum.factory_title']);
        static::assertSame('paypal', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
    }

    public function testCreateConfigKeepsTheGivenChannel(): void
    {
        $config = (new PaypalGatewayFactory())->createConfig([
            AbstractPaymentApi::CONFIG_CHANNEL => 'paypal_checkout',
        ]);

        static::assertSame('paypal_checkout', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame('paypal', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
    }

    public function testCreateBuildsAGatewayWithoutCredentials(): void
    {
        $gateway = (new PaypalGatewayFactory())->create([
            AbstractPaymentApi::CONFIG_CHANNEL => 'paypal_checkout',
        ]);

        static::assertSame(Gateway::class, $gateway::class);
    }

    public function testTheGatewayRejectsRequestsNoneOfItsActionsSupports(): void
    {
        $gateway = (new PaypalGatewayFactory())->create();

        $this->expectException(RequestNotSupportedException::class);

        $gateway->execute(new Cancel(new \ArrayObject()));
    }
}
