<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Stats\Entity;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MessageTypeStatsEntity extends Struct
{
    public function __construct(
        public readonly string $type,
        public readonly int $count,
    ) {
    }
}
