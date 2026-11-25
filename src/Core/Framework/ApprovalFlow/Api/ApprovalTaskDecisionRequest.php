<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Api;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Typed transport envelope; command invariants remain in ApprovalInstanceService.
 *
 * @internal
 */
#[Package('framework')]
final class ApprovalTaskDecisionRequest
{
    #[Assert\NotNull]
    public string $commandId;

    #[Assert\NotNull]
    public int $expectedVersion;

    #[Assert\NotNull]
    public ApprovalOutcome $outcome;

    public string $reason = '';
}
