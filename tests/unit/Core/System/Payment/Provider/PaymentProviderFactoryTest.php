<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use Payum\Core\GatewayFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProviderFactory;
use Shopwell\Core\System\Payment\Provider\PaymentProviderSchemaReader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderFactory::class)]
class PaymentProviderFactoryTest extends TestCase
{
    private const ALIPAY_DECLARATION = __DIR__ . '/../../../../../../src/Core/System/Payment/Provider/Resources/config/alipay.xml';

    public function testCreateBuildsTheProviderFromItsDeclaration(): void
    {
        $gatewayFactory = static::createStub(GatewayFactoryInterface::class);
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);

        $factory = new PaymentProviderFactory(new PaymentProviderSchemaReader());

        $provider = $factory->create(self::ALIPAY_DECLARATION, $gatewayFactory, $extractor);

        static::assertSame('alipay', $provider->technicalName);
        static::assertSame('支付宝', $provider->label);
        static::assertSame($gatewayFactory, $provider->gatewayFactory);
        static::assertSame($extractor, $provider->notificationExtractor);
        static::assertSame(
            ['app_id', 'sandbox', 'private_key', 'alipay_public_key', 'sign_type', 'gateway_url'],
            $provider->credentialNames()
        );
        static::assertTrue($provider->hasChannel('alipay_h5'));
        static::assertFalse($provider->hasChannel('unknown'));
    }

    public function testCreateThrowsWhenTheDeclarationCannotBeRead(): void
    {
        $factory = new PaymentProviderFactory(new PaymentProviderSchemaReader());

        try {
            $factory->create('/does/not/exist/provider.xml', static::createStub(GatewayFactoryInterface::class), static::createStub(PaymentNotificationExtractorInterface::class));
            static::fail('Expected the factory to reject an unreadable declaration.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::INVALID_PROVIDER_DECLARATION, $exception->getErrorCode());
        }
    }
}
