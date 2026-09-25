<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\Events;

use Shopwell\Core\Framework\Adapter\Cache\StoreApiRouteCacheTagsEvent;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 *
 * @codeCoverageIgnore
 */
class ProductDetailRouteCacheTagsEvent extends StoreApiRouteCacheTagsEvent
{
}
