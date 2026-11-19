<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class EndNodeConfiguration implements NodeConfiguration
{
    public function __construct(public ApprovalOutcome $outcome)
    {
    }

    /**
     * @return array{outcome: 'approved'|'rejected'}
     */
    public function jsonSerialize(): array
    {
        return ['outcome' => $this->outcome->value];
    }
}
