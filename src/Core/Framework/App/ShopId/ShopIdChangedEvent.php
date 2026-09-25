<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\ShopId;

use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class ShopIdChangedEvent extends Event
{
    public function __construct(
        public readonly ShopId $newShopId,
        public readonly ?ShopId $oldShopId
    ) {
        if ($oldShopId !== null && $oldShopId->id === $newShopId->id) {
            throw AppException::invalidArgument('ShopIdChangedEvent requires a changed shop id');
        }
    }
}
