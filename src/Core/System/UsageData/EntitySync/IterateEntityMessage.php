<?php declare(strict_types=1);

namespace Shopwell\Core\System\UsageData\EntitySync;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\MessageQueue\LowPriorityMessageInterface;

/**
 * @internal
 */
#[Package('data-services')]
class IterateEntityMessage implements LowPriorityMessageInterface
{
    public readonly \DateTimeImmutable $runDate;

    public readonly ?\DateTimeImmutable $lastRun;

    public function __construct(
        public readonly string $entityName,
        public readonly Operation $operation,
        \DateTimeInterface $runDate,
        ?\DateTimeInterface $lastRun,
        public readonly ?string $shopId = null
    ) {
        $this->runDate = \DateTimeImmutable::createFromInterface($runDate);
        $this->lastRun = $lastRun ? \DateTimeImmutable::createFromInterface($lastRun) : null;
    }
}
