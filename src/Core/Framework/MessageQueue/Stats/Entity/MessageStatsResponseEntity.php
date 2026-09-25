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
class MessageStatsResponseEntity extends Struct
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?MessageStatsEntity $stats = null,
    ) {
    }
}
