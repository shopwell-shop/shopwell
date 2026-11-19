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
