<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffect;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Effect\NodeEffectValidator;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeConfigurationCompiler;
use Shopwell\Core\Framework\Log\Package;

/**
 * Restores one configuration from a verified persisted release and immediately returns its pure entry intent.
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
        private readonly NodeConfigurationCompiler $configurations,
        private readonly NodeExecutorRegistry $executors,
        private readonly NodeTypeRegistry $nodeTypes,
        private readonly NodeEffectValidator $effects,
    ) {
    }

    public function enter(ExecutableApprovalRelease $release, string $nodeId, NodeExecutionContext $context = new NodeExecutionContext()): NodeEffect
    {
        $node = $release->node($nodeId);
        $type = $this->nodeTypes->get($node['type'], $node['version']);
        $path = '/nodes/' . $node['index'] . '/config';
        $configuration = $this->configurations->compile($type->type, $type->version, $node['authoredJson'], $path);
        $frozen = $this->configurations->encode($type->type, $type->version, $configuration);
        $actual = json_encode($frozen, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
        if ($actual !== $node['compiledJson']) {
            throw ApprovalFlowException::releaseVerificationFailed($release->graph->releaseId, 'nodePlanJson/nodes/' . $node['index'] . '/configuration');
        }

        $effect = $this->executors->get($type->type, $type->version)->enter($configuration, $context);
        $this->effects->validate($type, $configuration, $effect);

        return $effect;
    }
}
