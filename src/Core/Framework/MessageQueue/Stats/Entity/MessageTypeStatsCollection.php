<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\MessageQueue\Stats\Entity;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<MessageTypeStatsEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MessageTypeStatsCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return MessageTypeStatsEntity::class;
    }
}
