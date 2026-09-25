<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Search;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final
 *
 * @phpstan-import-type SearchConfig from SearchConfigLoader
 */
#[Package('framework')]
class CachedSearchConfigLoader extends SearchConfigLoader
{
    final public const CACHE_KEY = 'search-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly SearchConfigLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        return $this->cache->get(self::CACHE_KEY, fn (): array => $this->decorated->load($context));
    }
}
