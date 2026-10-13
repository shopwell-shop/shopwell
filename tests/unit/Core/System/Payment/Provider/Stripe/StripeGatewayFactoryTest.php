<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Stripe;

use Payum\Core\Gateway;
use Payum\Core\Request\Capture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\AbstractPaymentApi;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayModel;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeApi;
use Shopwell\Core\System\Payment\Provider\Stripe\StripeGatewayFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StripeGatewayFactory::class)]
class StripeGatewayFactoryTest extends TestCase
{
    public function testProviderIsStripe(): void
    {
        static::assertSame('stripe', (new StripeGatewayFactory())->provider());
    }

    public function testCreateConfigFillsTheDefaults(): void
    {
        $config = (new StripeGatewayFactory())->createConfig();

        static::assertSame('stripe', $config['payum.factory_name']);
        static::assertSame('stripe', $config['payum.factory_title']);
        static::assertSame('stripe', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_NOTIFY_URL]);
        static::assertSame('', $config[AbstractPaymentApi::CONFIG_RETURN_URL]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
        static::assertSame([], $config[AbstractPaymentApi::CONFIG_SETTINGS]);
    }

    public function testCreateConfigKeepsTheGivenValues(): void
    {
        $config = (new StripeGatewayFactory())->createConfig([
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_sdk',
            AbstractPaymentApi::CONFIG_CREDENTIALS => [StripeApi::SECRET_KEY => 'sk_test_123'],
        ]);

        static::assertSame('stripe_sdk', $config[AbstractPaymentApi::CONFIG_CHANNEL]);
        static::assertSame([StripeApi::SECRET_KEY => 'sk_test_123'], $config[AbstractPaymentApi::CONFIG_CREDENTIALS]);
        static::assertSame('stripe', $config[AbstractPaymentApi::CONFIG_PROVIDER]);
    }

    public function testCreateBuildsAGateway(): void
    {
        static::assertInstanceOf(Gateway::class, (new StripeGatewayFactory())->create());
    }

    public function testTheGatewayCarriesTheApiAndTheCaptureAction(): void
    {
        $gateway = (new StripeGatewayFactory())->create([
            AbstractPaymentApi::CONFIG_CHANNEL => 'stripe_checkout',
        ]);

        $model = new \ArrayObject([
            PaymentGatewayModel::PAYMENT_NO => 'P-1',
            PaymentGatewayModel::AMOUNT => 1000,
            PaymentGatewayModel::CURRENCY => 'CNY',
            PaymentGatewayModel::SUBJECT => 'Order subject',
        ]);

        try {
            $gateway->execute(new Capture($model));
            static::fail('The capture was expected to fail without stripe credentials.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::GATEWAY_ERROR, $exception->getErrorCode());
        }

        $request = PaymentGatewayModel::requestOf($model);
        static::assertSame('cny', $request['currency']);
        static::assertSame(1000, $request['amount']);
        static::assertSame('redirect', $request['flow']);
    }
}
