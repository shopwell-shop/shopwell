<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Automation;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalAutomation
{
    private function __construct(public string $decision, public bool $approveInitiator, public string $deduplication)
    {
    }

    public static function fromConfiguration(mixed $configuration): self
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }
        if (!\is_array($configuration) || array_diff(array_keys($configuration), ['decision', 'approveInitiator', 'deduplication']) !== []) {
            throw WorkflowExecutionException::invalidGraph('Invalid automatic approval configuration.');
        }
        $configuration = array_replace(['decision' => 'manual', 'approveInitiator' => false, 'deduplication' => 'none'], $configuration);
        if (!\in_array($configuration['decision'], ['manual', 'approve', 'reject'], true)
            || !\is_bool($configuration['approveInitiator'])
            || !\in_array($configuration['deduplication'], ['none', 'adjacent', 'all'], true)) {
            throw WorkflowExecutionException::invalidGraph('Invalid automatic approval configuration.');
        }

        return new self($configuration['decision'], $configuration['approveInitiator'], $configuration['deduplication']);
    }

    /**
     * @return array{decision: string, approveInitiator: bool, deduplication: string}
     */
    public function configuration(): array
    {
        return ['decision' => $this->decision, 'approveInitiator' => $this->approveInitiator, 'deduplication' => $this->deduplication];
    }
}
