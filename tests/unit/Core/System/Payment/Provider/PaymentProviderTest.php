<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Product\PaymentInteraction;
use Shopwell\Core\System\Payment\Product\PaymentProduct;
use Shopwell\Core\System\Payment\Provider\Config\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProvider::class)]
class PaymentProviderTest extends TestCase
{
    public function testBindsServicesAndIndexesChannels(): void
    {
        $gatewayFactory = static::createStub(PaymentGatewayFactory::class);
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);

        $h5 = $this->channel('alipay_h5');
        $qr = $this->channel('alipay_qr');

        $provider = new PaymentProvider(
            technicalName: 'alipay',
            label: '支付宝',
            credentials: [new PaymentConfigField(name: 'app_id')],
            products: ['alipay_h5' => $h5, 'alipay_qr' => $qr],
            gatewayFactory: $gatewayFactory,
            notificationExtractor: $extractor,
        );

        static::assertSame('alipay', $provider->technicalName);
        static::assertSame('支付宝', $provider->label);
        static::assertSame($gatewayFactory, $provider->gatewayFactory);
        static::assertSame($extractor, $provider->notificationExtractor);
        static::assertSame(['alipay_h5' => $h5, 'alipay_qr' => $qr], $provider->products);
    }

    public function testWithoutChannelsYieldsEmptyMap(): void
    {
        $provider = new PaymentProvider(
            technicalName: 'paypal',
            label: 'PayPal',
            credentials: [],
            products: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );

        static::assertSame([], $provider->products);
        static::assertSame([], $provider->credentialNames());
    }

    public function testCredentialFieldReturnsTheMatchingField(): void
    {
        $field = new PaymentConfigField(name: 'private_key', type: PaymentConfigField::TYPE_TEXTAREA, required: true, secret: true);

        $provider = $this->provider(credentials: [$field, new PaymentConfigField(name: 'app_id')]);

        static::assertSame($field, $provider->credentialField('private_key'));
    }

    public function testCredentialFieldReturnsNullForUnknownField(): void
    {
        $provider = $this->provider(credentials: [new PaymentConfigField(name: 'app_id')]);

        static::assertNull($provider->credentialField('unknown'));
    }

    public function testHasChannelDistinguishesDeclaredAndUnknownChannels(): void
    {
        $provider = $this->provider(channels: ['alipay_h5' => $this->channel('alipay_h5')]);

        static::assertTrue($provider->hasProduct('alipay_h5'));
        static::assertFalse($provider->hasProduct('alipay_qr'));
    }

    public function testCredentialNamesKeepTheDeclarationOrder(): void
    {
        $provider = $this->provider(credentials: [
            new PaymentConfigField(name: 'app_id'),
            new PaymentConfigField(name: 'private_key'),
            new PaymentConfigField(name: 'alipay_public_key'),
        ]);

        static::assertSame(['app_id', 'private_key', 'alipay_public_key'], $provider->credentialNames());
    }

    /**
     * @param list<PaymentConfigField> $credentials
     * @param array<string, PaymentProduct> $channels
     */
    private function provider(array $credentials = [], array $channels = []): PaymentProvider
    {
        return new PaymentProvider(
            technicalName: 'alipay',
            label: '支付宝',
            credentials: $credentials,
            products: $channels,
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }

    private function channel(string $technicalName): PaymentProduct
    {
        return new PaymentProduct(
            technicalName: $technicalName,
            providerTechnicalName: 'alipay',
            method: 'alipay',
            label: $technicalName,
            interactions: [PaymentInteraction::REDIRECT],
        );
    }
}
