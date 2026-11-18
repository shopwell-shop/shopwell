<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Assignee;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class MissingApprovalAssigneePolicy
{
    public const BLOCK = 'block';
    public const APPROVE = 'approve';
    public const REJECT = 'reject';
    public const ASSIGN = 'assign';

    private function __construct(public string $action, public ?string $userId = null)
    {
    }

    public static function fromConfiguration(mixed $configuration): self
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }

        if (!\is_array($configuration) || array_diff(array_keys($configuration), ['action', 'userId']) !== []) {
            throw WorkflowException::invalidDocument('', 'A missing-assignee policy must be a configuration object.');
        }

        if ($configuration === []) {
            return new self(self::BLOCK);
        }

        $action = $configuration['action'] ?? null;
        if (!\in_array($action, [self::BLOCK, self::APPROVE, self::REJECT, self::ASSIGN], true)) {
            throw WorkflowException::invalidDocument('/action', 'Select a supported missing-assignee action.');
        }

        if ($action !== self::ASSIGN) {
            if (\array_key_exists('userId', $configuration)) {
                throw WorkflowException::invalidDocument('/userId', 'Only assignment policies may select a replacement reviewer.');
            }

            return new self($action);
        }

        $userId = $configuration['userId'] ?? null;
        if (!\is_string($userId) || !Uuid::isValid(strtolower($userId))) {
            throw WorkflowException::invalidDocument('/userId', 'Select a replacement reviewer.');
        }

        return new self($action, strtolower($userId));
    }

    /**
     * @return array{action: string, userId?: string}
     */
    public function configuration(): array
    {
        if ($this->userId !== null) {
            return ['action' => $this->action, 'userId' => $this->userId];
        }

        return ['action' => $this->action];
    }
}
