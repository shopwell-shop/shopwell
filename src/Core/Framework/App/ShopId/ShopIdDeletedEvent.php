<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ShopId;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ShopIdDeletedEvent extends Event
{
}
