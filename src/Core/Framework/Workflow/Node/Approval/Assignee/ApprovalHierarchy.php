<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalHierarchy
{
    private function __construct(public int $level, public bool $continuous)
    {
    }

    public static function fromConfiguration(mixed $configuration): self
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }
        if (!\is_array($configuration) || array_diff(array_keys($configuration), ['level', 'continuous']) !== []) {
            throw WorkflowException::invalidDocument('', 'A reporting hierarchy accepts only level and continuous.');
        }

        $level = \array_key_exists('level', $configuration) ? $configuration['level'] : 1;
        if (!\is_int($level) || $level < 1 || $level > 20) {
            throw WorkflowException::invalidDocument('/level', 'Select a reporting level between one and twenty.');
        }

        $continuous = \array_key_exists('continuous', $configuration) ? $configuration['continuous'] : false;
        if (!\is_bool($continuous)) {
            throw WorkflowException::invalidDocument('/continuous', 'Continuous approval must be a boolean.');
        }

        return new self($level, $continuous);
    }
}
