<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class FinanceNodeConfiguration implements NodeConfiguration
{
    public function __construct(public int $limit, public bool $enabled)
    {
    }

    public function jsonSerialize(): array
    {
        return ['limit' => $this->limit, 'enabled' => $this->enabled, 'note' => null, 'options' => new \stdClass(), 'groups' => []];
    }
}
