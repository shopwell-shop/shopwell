<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Binding\Registry;

use Shopwell\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CachedContentSystemBindingSpecificationRegistry extends AbstractContentSystemBindingSpecificationRegistry
{
    private const CACHE_KEY = 'content_system.binding_specifications';

    public function __construct(
        private readonly AbstractContentSystemBindingSpecificationRegistry $inner,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getDecorated(): AbstractContentSystemBindingSpecificationRegistry
    {
        return $this->inner;
    }

    /**
     * @return array<string, BindingSpecification>
     */
    public function all(): array
    {
        return $this->cache->get(self::CACHE_KEY, fn () => $this->inner->all());
    }

    public function invalidate(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
