<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use Payum\Core\GatewayFactoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentConfigField;
use Shopwell\Core\System\Payment\Provider\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderDefinition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProvider::class)]
class PaymentProviderTest extends TestCase
{
    public function testFromDefinitionBindsServicesAndIndexesChannels(): void
    {
        $gatewayFactory = static::createStub(GatewayFactoryInterface::class);
        $extractor = static::createStub(PaymentNotificationExtractorInterface::class);

        $h5 = $this->channel('alipay_h5');
        $qr = $this->channel('alipay_qr');

        $definition = new PaymentProviderDefinition(
            technicalName: 'alipay',
            label: '支付宝',
            credentials: [new PaymentConfigField(name: 'app_id')],
            channels: [$h5, $qr],
        );

        $provider = PaymentProvider::fromDefinition($definition, $gatewayFactory, $extractor);

        static::assertSame('alipay', $provider->technicalName);
        static::assertSame('支付宝', $provider->label);
        static::assertSame($gatewayFactory, $provider->gatewayFactory);
        static::assertSame($extractor, $provider->notificationExtractor);
        static::assertSame(['alipay_h5' => $h5, 'alipay_qr' => $qr], $provider->channels);
    }

    public function testFromDefinitionWithNoChannelsYieldsEmptyMap(): void
    {
        $provider = PaymentProvider::fromDefinition(
            new PaymentProviderDefinition(
                technicalName: 'paypal',
                label: 'PayPal',
                credentials: [],
                channels: [],
            ),
            static::createStub(GatewayFactoryInterface::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        );

        static::assertSame([], $provider->channels);
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
        $provider = $this->provider(channels: [$this->channel('alipay_h5')]);

        static::assertTrue($provider->hasChannel('alipay_h5'));
        static::assertFalse($provider->hasChannel('alipay_qr'));
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
     * @param list<PaymentChannel> $channels
     */
    private function provider(array $credentials = [], array $channels = []): PaymentProvider
    {
        return PaymentProvider::fromDefinition(
            new PaymentProviderDefinition(
                technicalName: 'alipay',
                label: '支付宝',
                credentials: $credentials,
                channels: $channels,
            ),
            static::createStub(GatewayFactoryInterface::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }

    private function channel(string $technicalName): PaymentChannel
    {
        return new PaymentChannel(
            technicalName: $technicalName,
            providerName: 'alipay',
            label: $technicalName,
            flow: PaymentFlow::REDIRECT,
        );
    }
}
