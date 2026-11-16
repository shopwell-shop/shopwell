<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval\Node;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * Publication inputs for a single node, independent of the designer implementation.
 */
#[Package('framework')]
final readonly class ApprovalNodeCompilationContext
{
    /**
     * @internal
     *
     * @param list<array<string, mixed>> $inputSchema
     */
    public function __construct(
        public string $nodeId,
        public mixed $rawConfiguration,
        public string $path,
        public array $inputSchema = [],
    ) {
    }

    /**
     * @param list<string> $allowedKeys
     *
     * @return array<string, mixed>
     */
    public function configuration(array $allowedKeys): array
    {
        $configuration = $this->rawConfiguration;

        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }

        if (!\is_array($configuration) || ($configuration !== [] && array_is_list($configuration))) {
            throw $this->reject('invalid_configuration');
        }

        $unknown = array_diff(array_keys($configuration), $allowedKeys);

        if ($unknown !== []) {
            throw $this->reject('unknown_configuration', '/' . reset($unknown));
        }

        if (\array_key_exists('label', $configuration)
            && (!\is_string($configuration['label']) || mb_strlen($configuration['label']) > 255)) {
            throw $this->reject('invalid_label', '/label');
        }

        if (\array_key_exists('description', $configuration) && !\is_string($configuration['description'])) {
            throw $this->reject('invalid_description', '/description');
        }

        return $configuration;
    }

    public function reject(string $code, string $suffix = ''): HttpException
    {
        return WorkflowException::publicationRejected('unsupported_approval_workflow', [
            new WorkflowViolation($code, $this->path . $suffix, $this->nodeId),
        ]);
    }
}
