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

    public string $contractJson;

    public string $graphPlanJson;

    public string $nodePlanJson;

    public string $hash;

    public function __construct(
        CompiledNodePlan $nodePlan,
        public string $dependenciesJson,
    ) {
        $graphPlan = $nodePlan->graphPlan;
        $snapshot = $graphPlan->snapshot;
        $this->graphJson = $snapshot->graphJson;
        $this->contractJson = $snapshot->contractJson;
        $this->graphPlanJson = $graphPlan->json;
        $this->nodePlanJson = $nodePlan->json;
        $this->hash = Hasher::hash([
            'formatVersion' => '1.0.0',
            'nodePlanHash' => $nodePlan->hash,
            'dependenciesJson' => $dependenciesJson,
        ], 'sha256');
    }
}
