<?php declare(strict_types=1);

namespace Shopwell\Core\System\Language\Event;

use Shopwell\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@discovery')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class LanguageRouteCacheKeyEvent extends StoreApiRouteCacheKeyEvent
{
}
