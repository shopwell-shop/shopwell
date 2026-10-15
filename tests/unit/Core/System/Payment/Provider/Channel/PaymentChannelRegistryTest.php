<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannel;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentChannelRegistry;
use Shopwell\Core\System\Payment\Provider\Channel\PaymentFlow;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentChannelRegistry::class)]
class PaymentChannelRegistryTest extends TestCase
{
    public function testIndexesEveryChannelOfEveryProvider(): void
    {
        $registry = $this->createRegistry([
            $this->createProvider('alipay', [$this->createChannel('alipay_h5', 'alipay')]),
            $this->createProvider('wechat', [$this->createChannel('wechat_native', 'wechat')]),
        ]);

        static::assertSame(['alipay_h5', 'wechat_native'], $registry->names());
        static::assertArrayHasKey('alipay_h5', $registry->all());
        static::assertTrue($registry->has('wechat_native'));
        static::assertSame('wechat', $registry->get('wechat_native')->providerTechnicalName);
    }

    public function testNamesOfAnEmptyRegistryAreEmpty(): void
    {
        $registry = $this->createRegistry([]);

        static::assertSame([], $registry->names());
        static::assertSame([], $registry->all());
        static::assertFalse($registry->has('alipay_h5'));
    }

    public function testGetThrowsForAnUnknownChannel(): void
    {
        $registry = $this->createRegistry([
            $this->createProvider('alipay', [$this->createChannel('alipay_h5', 'alipay')]),
        ]);

        $this->expectExceptionObject(PaymentException::unknownChannel('alipay_wap'));

        $registry->get('alipay_wap');
    }

    public function testOfProviderReturnsOnlyTheChannelsOfThatProvider(): void
    {
        $registry = $this->createRegistry([
            $this->createProvider('alipay', [
                $this->createChannel('alipay_h5', 'alipay'),
                $this->createChannel('alipay_wap', 'alipay'),
            ]),
            $this->createProvider('wechat', [$this->createChannel('wechat_native', 'wechat')]),
        ]);

        $channels = $registry->ofProvider('alipay');

        static::assertCount(2, $channels);
        static::assertSame('alipay_h5', $channels[0]->technicalName);
        static::assertSame('alipay_wap', $channels[1]->technicalName);
        static::assertSame([], $registry->ofProvider('paypal'));
    }

    public function testADuplicateChannelNameRefusesTheRegistry(): void
    {
        $this->expectExceptionObject(PaymentException::duplicateChannel('alipay_h5', 'alipay', 'alipay_extension'));

        $this->createRegistry([
            $this->createProvider('alipay', [$this->createChannel('alipay_h5', 'alipay')]),
            $this->createProvider('alipay_extension', [$this->createChannel('alipay_h5', 'alipay_extension')]),
        ]);
    }

    /**
     * @param list<PaymentProvider> $providers
     */
    private function createRegistry(array $providers): PaymentChannelRegistry
    {
        return new PaymentChannelRegistry(new PaymentProviderRegistry($providers));
    }

    /**
     * @param list<PaymentChannel> $channels
     */
    private function createProvider(string $technicalName, array $channels): PaymentProvider
    {
        $indexed = [];
        foreach ($channels as $channel) {
            $indexed[$channel->technicalName] = $channel;
        }

        return new PaymentProvider(
            $technicalName,
            $technicalName,
            [],
            $indexed,
            static::createStub(PaymentGatewayFactory::class),
            static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }

    private function createChannel(string $technicalName, string $providerTechnicalName): PaymentChannel
    {
        return new PaymentChannel($technicalName, $providerTechnicalName, $technicalName, PaymentFlow::REDIRECT);
    }
}
