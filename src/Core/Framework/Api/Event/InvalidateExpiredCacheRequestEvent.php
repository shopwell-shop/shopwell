<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Event;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class InvalidateExpiredCacheRequestEvent
{
    /**
     * @internal Constructor for internal use only.
     */
    public function __construct(
        public readonly Request $request
    ) {
    }
}
