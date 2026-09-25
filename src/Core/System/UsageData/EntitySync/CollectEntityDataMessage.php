<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\EntitySync;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 */
#[Package('data-services')]
class CollectEntityDataMessage implements LowPriorityMessageInterface
{
    public function __construct(public readonly ?string $shopId = null)
    {
    }
}
