<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\AdvanceEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\CompleteEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffectValidator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\RequestApprovalEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeConfigurationCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\Log\Package;

/**
 * Restores one configuration from a verified persisted release and immediately returns its pure entry intent.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 * @phpstan-import-type NodePlan from NodePlanCompiler
 *
 * @phpstan-type EnteredSerialRoute array{nodes: non-empty-list<array{nodeId: string, connectionId: string}>, effect: RequestApprovalEffect|CompleteEffect}
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntryTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 */
#[Package('framework')]
class ApprovalNodeEntry
{
    public function __construct(
        private readonly ApprovalReleaseLoader $releases,
        private readonly NodeConfigurationCompiler $configurations,
        private readonly NodeExecutorRegistry $executors,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly NodeTypeRegistry $nodeTypes,
        private readonly NodeEffectValidator $effects,
    ) {
    }

    public function enter(string $releaseId, string $nodeId, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect|RequestApprovalEffect|CompleteEffect
    {
        $release = $this->releases->load($releaseId);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);

        return $this->enterNode($releaseId, $nodeId, $release, $graph, $context);
    }

    /**
     * @return array{startNodeId: string, connectionId: string, nextNodeId: string}
     */
    public function enterStart(string $releaseId): array
    {
        $release = $this->releases->load($releaseId);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        /** @var object{startNodeId: string} $plan */
        $plan = $this->jsonDecoder->decode($release->graphPlanJson);

        $route = $this->singleSuccessor($plan->startNodeId, $graph);
        if ($route === null) {
            throw ApprovalFlowException::unsupportedStartRoute($releaseId);
        }

        $effect = $this->enterNode($releaseId, $plan->startNodeId, $release, $graph);
        if (!$effect instanceof AdvanceEffect) {
            throw ApprovalFlowException::invalidGraphPlan('The start node must advance to its successor.');
        }

        return ['startNodeId' => $plan->startNodeId, ...$route];
    }

    /**
     * Reads a published route without re-entering the completed node's executor.
     *
     * @return array{connectionId: string, nextNodeId: string}
     */
    public function successor(string $releaseId, string $nodeId): array
    {
        $release = $this->releases->load($releaseId);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        $route = $this->singleSuccessor($nodeId, $graph);
        if ($route === null) {
            throw ApprovalFlowException::unsupportedNodeRoute($releaseId, $nodeId);
        }

        return $route;
    }

    /**
     * Enters serial successors once, stopping at the first approval or the published end boundary.
     *
     * @return EnteredSerialRoute
     */
    public function enterSuccessors(string $releaseId, string $completedNodeId, NodeExecutionContext $context = new NodeExecutionContext()): array
    {
        $release = $this->releases->load($releaseId);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        /** @var object{endNodeId: string} $plan */
        $plan = $this->jsonDecoder->decode($release->graphPlanJson);
        $nodes = [];
        $nodeId = $completedNodeId;
        $routing = new AdvanceEffect();

        // Publication proves acyclicity; a routing extension selects its output from the frozen form.
        while (true) {
            $route = $this->singleSuccessor($nodeId, $graph, $routing->outputPorts);
            if ($route === null) {
                throw ApprovalFlowException::unsupportedNodeRoute($releaseId, $nodeId);
            }
            $nodeId = $route['nextNodeId'];
            $effect = $this->enterNode($releaseId, $nodeId, $release, $graph, $context);
            $nodes[] = ['nodeId' => $nodeId, 'connectionId' => $route['connectionId']];
            if ($effect instanceof AdvanceEffect) {
                $routing = $effect;
                continue;
            }
            if ($effect instanceof CompleteEffect && $nodeId !== $plan->endNodeId) {
                throw ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.');
            }

            return ['nodes' => $nodes, 'effect' => $effect];
        }
    }

    public function enterEnd(string $releaseId, string $nodeId): CompleteEffect
    {
        $release = $this->releases->load($releaseId);
        /** @var object{endNodeId: string} $plan */
        $plan = $this->jsonDecoder->decode($release->graphPlanJson);
        if ($nodeId !== $plan->endNodeId) {
            throw ApprovalFlowException::invalidGraphPlan('Only the published end boundary may complete the process.');
        }
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        $effect = $this->enterNode($releaseId, $nodeId, $release, $graph);
        if (!$effect instanceof CompleteEffect) {
            throw ApprovalFlowException::invalidGraphPlan('The end boundary must provide a process outcome.');
        }

        return $effect;
    }

    /**
     * @param GraphPayload $graph
     * @param list<string>|null $outputPorts
     *
     * @return array{connectionId: string, nextNodeId: string}|null
     */
    private function singleSuccessor(string $nodeId, \stdClass $graph, ?array $outputPorts = null): ?array
    {
        $outgoing = [];
        foreach ($graph->connections as $connection) {
            if ($connection->source === $nodeId && ($outputPorts === null || \in_array($connection->sourcePort, $outputPorts, true))) {
                $outgoing[] = $connection;
            }
        }
        // Selected ports must resolve unambiguously; multiple activations need the execution-plan runner.
        if (\count($outgoing) !== 1) {
            return null;
        }
        $edge = $outgoing[0];

        return ['connectionId' => $edge->id, 'nextNodeId' => $edge->target];
    }

    /**
     * @param GraphPayload $graph
     */
    private function enterNode(string $releaseId, string $nodeId, CompiledApprovalRelease $release, \stdClass $graph, NodeExecutionContext $context = new NodeExecutionContext()): AdvanceEffect|RequestApprovalEffect|CompleteEffect
    {
        /** @var NodePlan $plan */
        $plan = $this->jsonDecoder->decode($release->nodePlanJson);
        foreach ($graph->nodes as $index => $node) {
            if ($node->id !== $nodeId) {
                continue;
            }

            $json = json_encode($node->config, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            $configuration = $this->configurations->compile($node->type, $node->typeVersion, $json, '/nodes/' . $index . '/config');
            $frozen = $this->configurations->encode($node->type, $node->typeVersion, $configuration);
            $actual = json_encode($frozen, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            $expected = json_encode($plan->nodes[$index]->configuration, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            if ($actual !== $expected) {
                throw ApprovalFlowException::releaseVerificationFailed($releaseId, 'nodePlanJson/nodes/' . $index . '/configuration');
            }

            $effect = $this->executors->get($node->type, $node->typeVersion)->enter($configuration, $context);
            $this->effects->validate($this->nodeTypes->get($node->type, $node->typeVersion), $configuration, $effect);

            return $effect;
        }

        throw ApprovalFlowException::releaseNodeNotFound($releaseId, $nodeId);
    }
}
