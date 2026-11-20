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
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Restores one configuration from a verified persisted release and immediately returns its pure entry intent.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 * @phpstan-import-type NodePlan from NodePlanCompiler
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalNodeEntryTest
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

    public function enter(string $releaseId, string $nodeId, Context $context): AdvanceEffect|RequestApprovalEffect|CompleteEffect
    {
        $release = $this->releases->load($releaseId, $context);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);

        return $this->enterNode($releaseId, $nodeId, $release, $graph);
    }

    /**
     * @return array{startNodeId: string, connectionId: string, nextNodeId: string}
     */
    public function enterStart(string $releaseId, Context $context): array
    {
        $release = $this->releases->load($releaseId, $context);
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
    public function successor(string $releaseId, string $nodeId, Context $context): array
    {
        $release = $this->releases->load($releaseId, $context);
        /** @var GraphPayload $graph */
        $graph = $this->jsonDecoder->decode($release->graphJson);
        $route = $this->singleSuccessor($nodeId, $graph);
        if ($route === null) {
            throw ApprovalFlowException::unsupportedNodeRoute($releaseId, $nodeId);
        }

        return $route;
    }

    public function enterEnd(string $releaseId, string $nodeId, Context $context): CompleteEffect
    {
        $release = $this->releases->load($releaseId, $context);
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
     *
     * @return array{connectionId: string, nextNodeId: string}|null
     */
    private function singleSuccessor(string $nodeId, \stdClass $graph): ?array
    {
        $outgoing = [];
        foreach ($graph->connections as $connection) {
            if ($connection->source === $nodeId) {
                $outgoing[] = $connection;
            }
        }
        // Release verification admits only unconditional connections in this runtime increment.
        if (\count($outgoing) !== 1) {
            return null;
        }
        $edge = $outgoing[0];
        foreach ($graph->connections as $connection) {
            if ($connection->target === $edge->target && $connection->id !== $edge->id) {
                return null;
            }
        }

        return ['connectionId' => $edge->id, 'nextNodeId' => $edge->target];
    }

    /**
     * @param GraphPayload $graph
     */
    private function enterNode(string $releaseId, string $nodeId, CompiledApprovalRelease $release, \stdClass $graph): AdvanceEffect|RequestApprovalEffect|CompleteEffect
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

            $effect = $this->executors->get($node->type, $node->typeVersion)->enter($configuration);
            $this->effects->validate($this->nodeTypes->get($node->type, $node->typeVersion), $configuration, $effect);

            return $effect;
        }

        throw ApprovalFlowException::releaseNodeNotFound($releaseId, $nodeId);
    }
}
