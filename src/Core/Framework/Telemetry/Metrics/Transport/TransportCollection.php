<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Telemetry\Metrics\Transport;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Telemetry\Metrics\Config\TransportConfigProvider;
use Shopwell\Core\Framework\Telemetry\Metrics\Factory\MetricTransportFactoryInterface;
use Shopwell\Core\Framework\Telemetry\Metrics\MetricTransportInterface;

/**
 * @template MetricTransport of MetricTransportInterface
 *
 * @implements \IteratorAggregate<int, MetricTransport>
 *
 * @internal
 */
#[Package('framework')]
class TransportCollection implements \IteratorAggregate
{
    /**
     * @param array<MetricTransport> $transports
     */
    private function __construct(private readonly array $transports)
    {
    }

    /**
     * @param \Traversable<MetricTransportFactoryInterface> $transportFactories
     *
     * @return TransportCollection<MetricTransportInterface>
     */
    public static function create(\Traversable $transportFactories, TransportConfigProvider $configProvider): TransportCollection
    {
        $config = $configProvider->getTransportConfig();
        $transports = array_map(
            static fn (MetricTransportFactoryInterface $factory): MetricTransportInterface => $factory->create($config),
            iterator_to_array($transportFactories)
        );

        return new self($transports);
    }

    /**
     * @return \Traversable<int, MetricTransport>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->transports);
    }
}
