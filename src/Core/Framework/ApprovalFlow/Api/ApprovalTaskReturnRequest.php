<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class ApprovalTaskReturnRequest
{
    #[Assert\NotNull]
    public string $commandId;

    #[Assert\NotNull]
    public int $expectedVersion;

    #[Assert\NotNull]
    public string $targetExecutionId;

    #[Assert\NotNull]
    public string $reason;
}
