<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\Log\Package;

/**
 * Compiles structural graph indexes, without interpreting node configuration or executing a node.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class GraphPlanCompiler
{
    public const ID = 'core:graph-plan';

    public const VERSION = '1.0.0';

    public function __construct(
        private readonly GraphPublicationPreflight $preflight,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function compile(DraftGraph $draft): CompiledGraphPlan
    {
        $snapshot = $this->preflight->prepare($draft);
        // Preflight established the complete closed graph shape, exact declarations and acyclic references.
        /** @var GraphPayload $payload */
        $payload = $this->jsonDecoder->decode($snapshot->graphJson);

        $incoming = [];
        $outgoing = [];
        $boundaries = [];
        foreach ($payload->nodes as $node) {
            $incoming[$node->id] = [];
            $outgoing[$node->id] = [];
            if ($node->type === CoreNodeType::START || $node->type === CoreNodeType::END) {
                $boundaries[$node->type] = $node->id;
            }
        }
        // Missing boundaries after successful preflight are an internal producer fault, not a client defect.
        if (!isset($boundaries[CoreNodeType::START], $boundaries[CoreNodeType::END])) {
            throw ApprovalFlowException::invalidGraphPlan('Preflight did not provide both boundary nodes.');
        }

        $connections = [];
        foreach ($payload->connections as $index => $connection) {
            $connections[$connection->id] = $index;
            $outgoing[$connection->source][] = $connection->id;
            $incoming[$connection->target][] = $connection->id;
        }

        $nodes = [];
        $incomingCounts = [];
        $ready = [];
        foreach ($payload->nodes as $index => $node) {
            $nodes[$node->id] = ['index' => $index, 'incoming' => $incoming[$node->id], 'outgoing' => $outgoing[$node->id]];
            $incomingCounts[$node->id] = \count($incoming[$node->id]);
            if ($incomingCounts[$node->id] === 0) {
                $ready[] = $node->id;
            }
        }

        // FIFO discovery follows authored connection order; this order is not a runtime execution schedule.
        for ($index = 0; $index < \count($ready); ++$index) {
            foreach ($nodes[$ready[$index]]['outgoing'] as $connectionId) {
                $target = $payload->connections[$connections[$connectionId]]->target;
                --$incomingCounts[$target];
                if ($incomingCounts[$target] === 0) {
                    $ready[] = $target;
                }
            }
        }

        $json = json_encode([
            'formatVersion' => '1.0.0',
            'compilerId' => self::ID,
            'compilerVersion' => self::VERSION,
            'graphHash' => $snapshot->graphHash,
            'snapshotHash' => $snapshot->hash,
            'startNodeId' => $boundaries[CoreNodeType::START],
            'endNodeId' => $boundaries[CoreNodeType::END],
            // Schema rejects leading U+0000; numeric IDs must remain object keys in the encoded JSON.
            'nodes' => (object) $nodes,
            'connections' => (object) $connections,
            'topologicalOrder' => $ready,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return new CompiledGraphPlan($snapshot, $json);
    }
}
