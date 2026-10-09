<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\Context;

use Shopwell\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
class CachedSalesChannelContextFactory extends AbstractSalesChannelContextFactory
{
    final public const ALL_TAG = 'sales-channel-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalesChannelContextFactory $decorated,
        private readonly InvalidationRaceAwareCache $cache,
    ) {
    }

    public function getDecorated(): AbstractSalesChannelContextFactory
    {
        return $this->decorated;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $name = self::buildName($salesChannelId);

        if (!$this->isCacheable($options)) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        ksort($options);

        $key = implode('-', [$name, Hasher::hash($options)]);
        $tags = [$name, self::ALL_TAG];
        $fresh = null;

        $value = $this->cache->get($key, $tags, function () use ($token, $salesChannelId, $options, &$fresh): string {
            $fresh = $this->decorated->create($token, $salesChannelId, $options);

            return CacheValueCompressor::compress($fresh);
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
        if ($fresh instanceof SalesChannelContext) {
            return $fresh;
        }

        $context = CacheValueCompressor::uncompress($value);

        if (!$context instanceof SalesChannelContext) {
            return $this->getDecorated()->create($token, $salesChannelId, $options);
        }

        $context->assign(['token' => $token]);

        return $context;
    }

    public static function buildName(string $salesChannelId): string
    {
        return 'context-factory-' . $salesChannelId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function isCacheable(array $options): bool
    {
        return !isset($options[SalesChannelContextService::CUSTOMER_ID])
            && !isset($options[SalesChannelContextService::BILLING_ADDRESS_ID])
            && !isset($options[SalesChannelContextService::SHIPPING_ADDRESS_ID])
            && !isset($options[SalesChannelContextService::BILLING_ADDRESS])
            && !isset($options[SalesChannelContextService::SHIPPING_ADDRESS]);
    }
}
