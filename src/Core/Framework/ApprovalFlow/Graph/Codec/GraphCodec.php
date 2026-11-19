<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Codec;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\ViolationCategory;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\Log\Package;

/**
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
        $payload = $this->jsonDecoder->decode($json);
        $graph = new DraftGraph($json);
        foreach ($this->diagnostics->analyze($graph)->violations as $violation) {
            if ($violation->category === ViolationCategory::Structure) {
                throw ApprovalFlowException::invalidGraph($violation->path, $violation->message);
            }
        }

        return new DraftGraph($this->canonicalJson($payload));
    }

    public function encodeDraft(DraftGraph $graph): string
    {
        // Internal draft values can also come from read-only tools; never treat their constructor as write admission.
        $decoded = $this->decodeDraft($graph->json);
        foreach ($this->diagnostics->analyze($decoded)->violations as $violation) {
            if ($violation->category !== ViolationCategory::Draft) {
                throw ApprovalFlowException::invalidGraph($violation->path, $violation->message);
            }
        }

        return $decoded->json;
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
