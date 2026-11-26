<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Event\ApprovalReturnTargetsEvent;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Authorizes personal decisions and delegates internal progression with the caller's Context.
 *
 * @phpstan-import-type Target from ApprovalReturnTargetsEvent
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceServiceTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalCompletionTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalSerialProgressionTest
 */
#[Package('framework')]
class ApprovalInstanceService
{
    public const MANAGE_PRIVILEGE = 'approval_flow_instance:manage';

    public const DECIDE_PRIVILEGE = 'approval_flow_task:decide';

    public function __construct(private readonly ApprovalInstanceStore $store)
    {
    }

    public function enterStart(string $instanceId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        return $this->store->enterStart($instanceId, $commandId, $expectedVersion, $context);
    }

    public function enterApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        return $this->store->enterApproval($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }

    public function suspend(string $instanceId, string $commandId, int $expectedVersion, string $reason, Context $context): ApprovalInstanceCommandResult
    {
        $source = $context->getSource();
        if (!$source instanceof SystemSource && (!$source instanceof AdminApiSource || !$source->isAllowed(self::MANAGE_PRIVILEGE))) {
            throw ApprovalFlowException::missingPrivileges([self::MANAGE_PRIVILEGE]);
        }
        if ($expectedVersion < 1 || trim($reason) === '' || mb_strlen($reason) > 1000) {
            throw ApprovalFlowException::invalidInstanceCommand('A positive aggregate version and a non-blank reason of at most 1000 characters are required.');
        }
        $actor = $source instanceof AdminApiSource
            ? ['source' => 'admin-api', 'userId' => $source->getUserId(), 'integrationId' => $source->getIntegrationId()]
            : ['source' => 'system'];
        $actorJson = json_encode($actor, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $this->store->suspend($instanceId, $commandId, $expectedVersion, $reason, $actorJson, $context);
    }

    public function advanceApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        return $this->store->advanceApproval($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }

    public function progress(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        return $this->store->progress($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }

    public function enterEnd(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        return $this->store->enterEnd($instanceId, $executionId, $commandId, $expectedVersion, $context);
    }

    public function decide(string $instanceId, string $taskId, string $commandId, int $expectedVersion, ApprovalOutcome $outcome, string $reason, Context $context): ApprovalInstanceCommandResult
    {
        $userId = $this->taskActor($context);
        if ($expectedVersion < 1 || !Uuid::isValid($instanceId) || !Uuid::isValid($taskId) || !Uuid::isValid($commandId) || mb_strlen($reason) > 1000) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance, task and command IDs, a positive version and a reason of at most 1000 characters are required.');
        }
        $actorJson = json_encode(['source' => 'admin-api', 'userId' => $userId, 'integrationId' => null], \JSON_THROW_ON_ERROR);

        return $this->store->decide($instanceId, $taskId, $commandId, $expectedVersion, $outcome, $reason, $userId, $actorJson, $context);
    }

    /**
     * @return list<Target>
     */
    public function returnTargets(string $instanceId, string $taskId, Context $context): array
    {
        $userId = $this->taskActor($context);
        if (!Uuid::isValid($instanceId) || !Uuid::isValid($taskId)) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance and task IDs are required.');
        }

        return $this->store->returnTargets($instanceId, $taskId, $userId, $context);
    }

    public function returnApproval(string $instanceId, string $taskId, string $commandId, int $expectedVersion, string $targetExecutionId, string $reason, Context $context): ApprovalInstanceCommandResult
    {
        $userId = $this->taskActor($context);
        $reason = trim($reason);
        if ($expectedVersion < 1 || !Uuid::isValid($instanceId) || !Uuid::isValid($taskId) || !Uuid::isValid($commandId)
            || !Uuid::isValid($targetExecutionId) || $reason === '' || mb_strlen($reason) > 1000) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance, task, command and target IDs, a positive version and a non-blank reason of at most 1000 characters are required.');
        }

        return $this->store->returnApproval($instanceId, $taskId, $commandId, $expectedVersion, $targetExecutionId, $reason, $userId, $context);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function transferCandidates(string $instanceId, string $taskId, string $term, Context $context): array
    {
        $userId = $this->taskActor($context);
        if (!Uuid::isValid($instanceId) || !Uuid::isValid($taskId)) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance and task IDs are required.');
        }

        return $this->store->transferCandidates($instanceId, $taskId, $userId, trim($term));
    }

    public function transferTask(string $instanceId, string $taskId, string $commandId, int $expectedVersion, string $assigneeId, string $reason, Context $context): ApprovalInstanceCommandResult
    {
        $userId = $this->taskActor($context);
        $reason = trim($reason);
        if ($expectedVersion < 1 || !Uuid::isValid($instanceId) || !Uuid::isValid($taskId) || !Uuid::isValid($commandId)
            || !Uuid::isValid($assigneeId) || $reason === '' || mb_strlen($reason) > 1000) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance, task, command and assignee IDs, a positive version and a non-blank reason of at most 1000 characters are required.');
        }

        return $this->store->transferTask($instanceId, $taskId, $commandId, $expectedVersion, $assigneeId, $reason, $userId, $context);
    }

    private function taskActor(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || !$source->isAllowed(self::DECIDE_PRIVILEGE) || $source->getUserId() === null || $source->getIntegrationId() !== null) {
            throw ApprovalFlowException::missingPrivileges([self::DECIDE_PRIVILEGE]);
        }

        return $source->getUserId();
    }
}
