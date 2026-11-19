<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Derived graph indexes bound to their immutable source snapshot, not an executable release.
 *
 * @internal
 */
#[Package('framework')]
final readonly class CompiledGraphPlan
{
    public string $hash;

    public function __construct(
        public GraphPublicationSnapshot $snapshot,
        public string $json,
    ) {
        $this->hash = Hasher::hash($json, 'sha256');
    }
}
