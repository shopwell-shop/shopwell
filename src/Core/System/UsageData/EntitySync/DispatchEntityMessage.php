<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\EntitySync;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 */
#[Package('data-services')]
class DispatchEntityMessage implements LowPriorityMessageInterface
{
    public readonly \DateTimeImmutable $runDate;

    /**
     * @param array<int, array<string, string>> $primaryKeys
     */
    public function __construct(
        public readonly string $entityName,
        public readonly Operation $operation,
        \DateTimeInterface $runDate,
        public readonly array $primaryKeys,
        public readonly ?string $shopId = null
    ) {
        $this->runDate = \DateTimeImmutable::createFromInterface($runDate);
    }
}
