<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Telemetry\Metrics\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\MetricConfig;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfig;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopwell\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopwell\Core\Framework\Telemetry\Metrics\Metric\Type;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopwell\Core\Framework\Telemetry\Metrics\Transport\TransportCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TransportCollection::class)]
class TransportCollectionTest extends TestCase
{
    public function testCreate(): void
    {
        $config = new TransportConfig(
            [MetricConfig::fromDefinition('test', ['type' => Type::GAUGE->value, 'description' => 'test', 'enabled' => true])]
        );

        $configProvider = $this->createMock(TransportConfigProvider::class);
        $configProvider->expects($this->once())
            ->method('getTransportConfig')
            ->willReturn($config);

        $transport1 = static::createStub(MetricTransportInterface::class);
        $transport2 = static::createStub(MetricTransportInterface::class);

        $factory1 = $this->createMock(MetricTransportFactoryInterface::class);
        $factory1->expects($this->once())
            ->method('create')
            ->with($config)
            ->willReturn($transport1);

        $factory2 = $this->createMock(MetricTransportFactoryInterface::class);
        $factory2->expects($this->once())
            ->method('create')
            ->with($config)
            ->willReturn($transport2);

        $factories = new \ArrayIterator([$factory1, $factory2]);

        $collection = TransportCollection::create($factories, $configProvider);

        $transports = iterator_to_array($collection->getIterator());
        static::assertCount(2, $transports);
        static::assertSame($transport1, $transports[0]);
        static::assertSame($transport2, $transports[1]);
    }
}
