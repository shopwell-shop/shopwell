<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Frozen derived configuration bytes bound to a graph plan, without release or execution authority.
 *
 * @internal
 */
#[Package('framework')]
final readonly class CompiledNodePlan
{
    public string $hash;

    public function __construct(
        public CompiledGraphPlan $graphPlan,
        public string $json,
    ) {
        $this->hash = Hasher::hash($json, 'sha256');
    }
}
