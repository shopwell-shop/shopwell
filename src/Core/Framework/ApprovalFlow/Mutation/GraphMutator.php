<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Mutation;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;

/**
 * Operations work on a detached wire copy and admit the entire result before returning it.
 * Defaults, storage, actor permissions and loss confirmation belong to their eventual callers.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class GraphMutator
{
    public function __construct(
        private readonly GraphCodec $codec,
        private readonly GraphDiagnostics $diagnostics,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function addNode(DraftGraph $graph, string $nodeJson): GraphMutationResult
    {
        $payload = $this->decodeWritablePayload($graph);
        $payload->nodes[] = $this->decodeFragment($nodeJson, '/nodes/' . \count($payload->nodes));

        return $this->createResult($payload);
    }

    public function addConnection(DraftGraph $graph, string $connectionJson): GraphMutationResult
    {
        $payload = $this->decodeWritablePayload($graph);
        $payload->connections[] = $this->decodeFragment($connectionJson, '/connections/' . \count($payload->connections));

        return $this->createResult($payload);
    }

    public function removeNode(DraftGraph $graph, string $nodeId): GraphMutationResult
    {
        $payload = $this->decodeWritablePayload($graph);
        $removedNodes = [];
        foreach ($payload->nodes as $index => $node) {
            if ($node->id === $nodeId) {
                $removedNodes[] = $this->encodeObject($node);
                unset($payload->nodes[$index]);
            }
        }
        if ($removedNodes === []) {
            throw ApprovalFlowException::invalidGraph('/nodes', 'Node ' . $nodeId . ' does not exist.');
        }
        $removedConnections = [];
        foreach ($payload->connections as $index => $connection) {
            if ($connection->source === $nodeId || $connection->target === $nodeId) {
                $removedConnections[] = $this->encodeObject($connection);
                unset($payload->connections[$index]);
            }
        }
        $removedLayout = [];
        foreach ($payload->layout->nodes as $index => $entry) {
            if ($entry->nodeId === $nodeId) {
                $removedLayout[] = $this->encodeObject($entry);
                unset($payload->layout->nodes[$index]);
            }
        }
        $payload->nodes = array_values($payload->nodes);
        $payload->connections = array_values($payload->connections);
        $payload->layout->nodes = array_values($payload->layout->nodes);

        return $this->createResult($payload, $removedNodes, $removedConnections, $removedLayout);
    }

    public function removeConnection(DraftGraph $graph, string $connectionId): GraphMutationResult
    {
        $payload = $this->decodeWritablePayload($graph);
        $removed = [];
        foreach ($payload->connections as $index => $connection) {
            if ($connection->id === $connectionId) {
                $removed[] = $this->encodeObject($connection);
                unset($payload->connections[$index]);
            }
        }
        if ($removed === []) {
            throw ApprovalFlowException::invalidGraph('/connections', 'Connection ' . $connectionId . ' does not exist.');
        }
        $payload->connections = array_values($payload->connections);

        return $this->createResult($payload, removedConnections: $removed);
    }

    /**
     * @return GraphPayload
     */
    private function decodeWritablePayload(DraftGraph $graph): \stdClass
    {
        $json = $this->codec->encodeDraft($graph);
        // Write admission above establishes the closed wire shape; decoding gives this operation its own copy.
        /** @var GraphPayload $payload */
        $payload = json_decode($json, flags: \JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function decodeFragment(string $json, string $path): \stdClass
    {
        try {
            $value = $this->jsonDecoder->decode($json);
        } catch (GraphJsonException $exception) {
            throw ApprovalFlowException::invalidGraph($path . $exception->violation->path, $exception->violation->message, $exception);
        }
        if (!$value instanceof \stdClass) {
            throw ApprovalFlowException::invalidGraph($path, 'Mutation fragment must be an object.');
        }

        return $value;
    }

    /**
     * @param list<string> $removedNodes
     * @param list<string> $removedConnections
     * @param list<string> $removedLayout
     */
    private function createResult(
        \stdClass $payload,
        array $removedNodes = [],
        array $removedConnections = [],
        array $removedLayout = [],
    ): GraphMutationResult {
        $mutatedDraft = new DraftGraph($this->encodeObject($payload));
        $encoded = $this->codec->encodeDraft($mutatedDraft);
        $graph = new DraftGraph($encoded);

        return new GraphMutationResult($graph, $this->diagnostics->analyze($graph), $removedNodes, $removedConnections, $removedLayout);
    }

    private function encodeObject(\stdClass $value): string
    {
        return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
    }
}
