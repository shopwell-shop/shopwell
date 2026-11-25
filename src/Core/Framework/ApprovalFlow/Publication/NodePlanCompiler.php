<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeConfigurationCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @phpstan-type NodePlan object{nodes: list<object{nodeId: string, type: string, typeVersion: string, configuration: \stdClass}>, policies: list<object{type: string, version: string}>}&\stdClass
 *
 * @internal
 */
#[Package('framework')]
class NodePlanCompiler
{
    public const ID = 'core:node-plan';

    public const VERSION = '1.0.0';

    public function __construct(
        private readonly GraphPlanCompiler $graphCompiler,
        private readonly NodeConfigurationCompiler $configurations,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly NodeExecutorRegistry $nodeExecutors,
    ) {
    }

    public function compile(DraftGraph $draft): CompiledNodePlan
    {
        $graphPlan = $this->graphCompiler->compile($draft);
        /** @var GraphPayload $payload */
        $payload = $this->jsonDecoder->decode($graphPlan->snapshot->graphJson);
        $nodes = [];
        $policies = [];
        foreach ($payload->nodes as $index => $node) {
            $this->nodeExecutors->get($node->type, $node->typeVersion);
            $json = json_encode($node->config, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
            $configuration = $this->configurations->compile($node->type, $node->typeVersion, $json, '/nodes/' . $index . '/config');
            if ($configuration instanceof ApprovalNodeConfiguration) {
                $policies[$configuration->policy . '@' . $configuration->policyVersion] = ['type' => $configuration->policy, 'version' => $configuration->policyVersion];
            }
            $nodes[] = [
                'nodeId' => $node->id,
                'type' => $node->type,
                'typeVersion' => $node->typeVersion,
                'configuration' => $this->configurations->encode($node->type, $node->typeVersion, $configuration),
            ];
        }

        $json = json_encode([
            'formatVersion' => '1.0.0',
            'compilerId' => self::ID,
            'compilerVersion' => self::VERSION,
            'graphPlanHash' => $graphPlan->hash,
            'nodes' => $nodes,
            'policies' => array_values($policies),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);

        return new CompiledNodePlan($graphPlan, $json);
    }
}
