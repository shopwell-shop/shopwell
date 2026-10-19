<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Payment\Gateway\PaymentGatewayFactory;
use Shopwell\Core\System\Payment\Notification\PaymentNotificationExtractorInterface;
use Shopwell\Core\System\Payment\PaymentException;
use Shopwell\Core\System\Payment\Provider\PaymentProvider;
use Shopwell\Core\System\Payment\Provider\PaymentProviderRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PaymentProviderRegistry::class)]
class PaymentProviderRegistryTest extends TestCase
{
    public function testIndexesTheProvidersByTechnicalName(): void
    {
        $alipay = $this->provider('alipay');
        $wechat = $this->provider('wechat');

        $registry = new PaymentProviderRegistry([$alipay, $wechat]);

        static::assertSame(['alipay' => $alipay, 'wechat' => $wechat], $registry->all());
    }

    public function testTheLastProviderWinsWhenATechnicalNameIsReused(): void
    {
        $first = $this->provider('alipay', 'first');
        $second = $this->provider('alipay', 'second');

        $registry = new PaymentProviderRegistry([$first, $second]);

        static::assertSame(['alipay' => $second], $registry->all());
    }

    public function testNamesReturnsTheTechnicalNames(): void
    {
        $registry = new PaymentProviderRegistry([$this->provider('alipay'), $this->provider('stripe')]);

        static::assertSame(['alipay', 'stripe'], $registry->names());
    }

    public function testHasReportsWhetherAProviderIsRegistered(): void
    {
        $registry = new PaymentProviderRegistry([$this->provider('alipay')]);

        static::assertTrue($registry->has('alipay'));
        static::assertFalse($registry->has('wechat'));
    }

    public function testGetReturnsTheRegisteredProvider(): void
    {
        $alipay = $this->provider('alipay');

        $registry = new PaymentProviderRegistry([$alipay]);

        static::assertSame($alipay, $registry->get('alipay'));
    }

    public function testGetThrowsForAnUnknownProvider(): void
    {
        $registry = new PaymentProviderRegistry([$this->provider('alipay')]);

        $this->expectExceptionObject(PaymentException::unknownProvider('wechat'));

        $registry->get('wechat');
    }

    public function testAnEmptyRegistryHasNothing(): void
    {
        $registry = new PaymentProviderRegistry([]);

        static::assertSame([], $registry->all());
        static::assertSame([], $registry->names());
        static::assertFalse($registry->has('alipay'));
    }

    private function provider(string $technicalName, string $label = 'label'): PaymentProvider
    {
        return new PaymentProvider(
            technicalName: $technicalName,
            label: $label,
            credentials: [],
            channels: [],
            gatewayFactory: static::createStub(PaymentGatewayFactory::class),
            notificationExtractor: static::createStub(PaymentNotificationExtractorInterface::class),
        );
    }
}
