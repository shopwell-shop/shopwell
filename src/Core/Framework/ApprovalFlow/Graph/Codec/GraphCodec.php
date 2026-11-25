<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Codec;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnosticsReport;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 */
#[Package('framework')]
class GraphCodec
{
    public function __construct(
        private readonly GraphDiagnostics $diagnostics,
        private readonly GraphJsonDecoder $jsonDecoder,
    ) {
    }

    public function decodeDraft(string $json): DraftGraph
    {
        return $this->readDraft($json)['graph'];
    }

    /**
     * @return array{graph: DraftGraph, diagnostics: GraphDiagnosticsReport}
     */
    public function readDraft(string $json): array
    {
        $payload = $this->jsonDecoder->decode($json);
        $report = $this->diagnostics->analyzePayload($payload);
        $this->requireStructure($report);

        return ['graph' => new DraftGraph($this->canonicalJson($payload)), 'diagnostics' => $report];
    }

    public function encodeDraft(DraftGraph $graph): string
    {
        return $this->prepareDraft($graph->json)['graph']->json;
    }

    /**
     * @return array{graph: DraftGraph, diagnostics: GraphDiagnosticsReport}
     */
    public function prepareDraft(string $json): array
    {
        return $this->prepareWrite($this->jsonDecoder->decode($json));
    }

    /**
     * @return array{graph: DraftGraph, payload: GraphPayload}
     */
    public function preparePublication(DraftGraph $draft): array
    {
        try {
            $payload = $this->jsonDecoder->decode($draft->json);
        } catch (GraphJsonException $exception) {
            $violation = $exception->violation;

            throw ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message);
        }

        $report = $this->diagnostics->analyzePayload($payload);
        if ($report->violations !== []) {
            $violation = $report->violations[0];

            throw ApprovalFlowException::publicationGraphInvalid($violation->code, $violation->path, $violation->message);
        }

        /** @var GraphPayload $payload */
        return ['graph' => new DraftGraph($this->canonicalJson($payload)), 'payload' => $payload];
    }

    /**
     * @return GraphPayload
     */
    public function decodeWritable(DraftGraph $graph): \stdClass
    {
        $payload = $this->jsonDecoder->decode($graph->json);
        $this->requireWritable($this->diagnostics->analyzePayload($payload));

        // The wire schema establishes the shape before the payload can cross the write boundary.
        /** @var GraphPayload $payload */
        return $payload;
    }

    /**
     * @return array{graph: DraftGraph, diagnostics: GraphDiagnosticsReport}
     */
    public function prepareWrite(mixed $payload): array
    {
        $report = $this->diagnostics->analyzePayload($payload);
        $this->requireWritable($report);

        return ['graph' => new DraftGraph($this->canonicalJson($payload)), 'diagnostics' => $report];
    }

    private function requireWritable(GraphDiagnosticsReport $report): void
    {
        $this->requireStructure($report);
        foreach ($report->violations as $violation) {
            if ($violation->category !== ViolationCategory::Draft) {
                throw ApprovalFlowException::invalidGraph($violation->path, $violation->message);
            }
        }
    }

    private function requireStructure(GraphDiagnosticsReport $report): void
    {
        foreach ($report->violations as $violation) {
            if ($violation->category === ViolationCategory::Structure) {
                throw ApprovalFlowException::invalidGraph($violation->path, $violation->message);
            }
        }
    }

    private function canonicalJson(mixed $value): string
    {
        return json_encode($this->orderKeys($value), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
    }

    private function orderKeys(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, \SORT_STRING);
            $ordered = new \stdClass();
            foreach ($properties as $key => $property) {
                $ordered->{$key} = $this->orderKeys($property);
            }

            return $ordered;
        }
        if (\is_array($value)) {
            return array_map($this->orderKeys(...), $value);
        }

        return $value;
    }
}
