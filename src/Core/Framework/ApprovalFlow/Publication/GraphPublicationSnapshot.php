<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Immutable graph and declaration bytes, not an executable release or a runtime configuration handle.
 * Constructing this internal value does not replace GraphPublicationPreflight or later publication gates.
 *
 * @internal
 */
#[Package('framework')]
final readonly class GraphPublicationSnapshot
{
    public string $graphHash;

    public string $hash;

    public function __construct(
        public string $graphJson,
        public string $contractJson,
    ) {
        $this->graphHash = Hasher::hash($graphJson, 'sha256');
        $this->hash = Hasher::hash(['graph' => $graphJson, 'contract' => $contractJson], 'sha256');
    }
}
