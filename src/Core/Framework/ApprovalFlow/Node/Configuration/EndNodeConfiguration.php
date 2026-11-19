<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class EndNodeConfiguration implements NodeConfiguration
{
    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * @param self::APPROVED|self::REJECTED $outcome
     */
    public function __construct(public string $outcome)
    {
    }

    /**
     * @return array{outcome: self::APPROVED|self::REJECTED}
     */
    public function jsonSerialize(): array
    {
        return ['outcome' => $this->outcome];
    }
}
