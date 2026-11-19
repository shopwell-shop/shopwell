<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;

/**
 * Immutable compilation content. Construction grants neither publication nor activation authority.
 *
 * @internal
 */
#[Package('framework')]
final readonly class CompiledApprovalRelease
{
    public string $graphJson;

    public string $hash;

    public function __construct(
        public CompiledNodePlan $nodePlan,
        public string $dependenciesJson,
    ) {
        $this->graphJson = $nodePlan->graphPlan->snapshot->graphJson;
        $this->hash = Hasher::hash([
            'formatVersion' => '1.0.0',
            'nodePlanHash' => $nodePlan->hash,
            'dependenciesJson' => $dependenciesJson,
        ], 'sha256');
    }
}
