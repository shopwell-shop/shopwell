<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseStore;
use Shopwell\Core\Framework\Log\Package;

/**
 * Revalidates persisted compilation content against this deployment, without activation authority.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 * @phpstan-import-type NodePlan from NodePlanCompiler
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoaderTest
 */
#[Package('framework')]
class ApprovalReleaseLoader
{
    public function __construct(
        private readonly ApprovalReleaseStore $releases,
        private readonly ApprovalReleaseCompiler $compiler,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function load(string $releaseId): CompiledApprovalRelease
    {
        $stored = $this->releases->readContent($releaseId);
        $compiled = $this->compiler->compile(new DraftGraph($stored['graphJson']));
        $parts = [
            'graphJson' => [$stored['graphJson'], $compiled->graphJson],
            'contractJson' => [$stored['contractJson'], $compiled->contractJson],
            'graphPlanJson' => [$stored['graphPlanJson'], $compiled->graphPlanJson],
            'nodePlanJson' => [$stored['nodePlanJson'], $compiled->nodePlanJson],
            'dependenciesJson' => [$stored['dependenciesJson'], $compiled->dependenciesJson],
            'contentHash' => [$stored['contentHash'], $compiled->hash],
        ];
        foreach ($parts as $part => [$expected, $actual]) {
            if ($expected !== $actual) {
                throw ApprovalFlowException::releaseVerificationFailed($releaseId, $part);
            }
        }

        return $compiled;
    }

    public function loadExecutable(string $releaseId): ExecutableApprovalRelease
    {
        $release = $this->load($releaseId);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        /** @var object{startNodeId: string, endNodeId: string} $boundaries */
        $boundaries = $this->jsonDecoder->decode($release->graphPlanJson);
        /** @var NodePlan $plan */
        $plan = $this->jsonDecoder->decode($release->nodePlanJson);

        $nodes = [];
        $flags = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION;
        foreach ($graph->nodes as $index => $node) {
            $nodes[$node->id] = [
                'type' => $node->type,
                'version' => $node->typeVersion,
                'authoredJson' => json_encode($node->config, $flags),
                'compiledJson' => json_encode($plan->nodes[$index]->configuration, $flags),
                'index' => $index,
            ];
        }

        return new ExecutableApprovalRelease(new ApprovalExecutionGraph($releaseId, $boundaries->startNodeId, $boundaries->endNodeId, $graph), $nodes);
    }
}
