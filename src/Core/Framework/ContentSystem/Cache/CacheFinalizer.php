<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\Cache;

use Shopwell\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class CacheFinalizer
{
    public function __construct(
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function finalize(Request $request, RenderingCacheContext $cacheContext): void
    {
        if ($cacheContext->isDisabled()) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, false);

            return;
        }

        $this->cacheTagCollector->addTag(...$cacheContext->getTags());
    }
}
