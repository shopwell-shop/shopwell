<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * Freezes complete graph input for publication; executable dependencies require their own admission.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class GraphPublicationPreflight
{
    public function __construct(
        private readonly GraphCodec $codec,
        private readonly GraphDiagnostics $diagnostics,
        private readonly NodeTypeRegistry $nodeTypes,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly string $graphSchema,
    ) {
    }

    public function prepare(DraftGraph $draft): GraphPublicationSnapshot
    {
        $report = $this->diagnostics->analyze($draft);
        if ($report->violations !== []) {
            $violation = $report->violations[0];

            throw ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message);
        }

        $graph = $this->codec->decodeDraft($draft->json);
        // Diagnostics admitted the closed graph schema and complete configurations above.
        /** @var GraphPayload $payload */
        $payload = $this->jsonDecoder->decode($graph->json);
        $declarations = [];
        foreach ($payload->nodes as $node) {
            $identity = $node->type . '@' . $node->typeVersion;
            if (isset($declarations[$identity])) {
                continue;
            }

            $type = $this->nodeTypes->get($node->type, $node->typeVersion);
            $ports = $type->ports;
            ksort($ports, \SORT_STRING);
            $declaredPorts = [];
            foreach ($ports as $id => $direction) {
                $declaredPorts[] = ['id' => $id, 'direction' => $direction];
            }
            $allowedEffects = array_map(static fn (NodeEffectType $effect): string => $effect->value, $type->allowedEffects);
            sort($allowedEffects, \SORT_STRING);
            $declarations[$identity] = [
                'type' => $type->type,
                'version' => $type->version,
                'configurationSchemaId' => $type->configurationSchemaId,
                'configurationSchemaHash' => $type->configurationSchemaHash,
                'configurationSchema' => $type->configurationSchema,
                'ports' => $declaredPorts,
                'allowedEffects' => $allowedEffects,
            ];
        }
        ksort($declarations, \SORT_STRING);

        $contractJson = json_encode([
            'formatVersion' => '1.0.0',
            'graphSchema' => $this->graphSchema,
            'nodeTypes' => array_values($declarations),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return new GraphPublicationSnapshot($graph->json, $contractJson);
    }
}
