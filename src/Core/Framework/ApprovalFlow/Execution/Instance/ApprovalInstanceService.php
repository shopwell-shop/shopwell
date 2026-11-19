<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalOutcome;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Handles administrative suspension and system node entry for an admitted instance.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceServiceTest
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
        $this->validateSystemCommand($instanceId, $commandId, $expectedVersion, $context);

        return $this->store->enterStart($instanceId, $commandId, $expectedVersion, $context);
    }

    public function enterApproval(string $instanceId, string $executionId, string $commandId, int $expectedVersion, Context $context): ApprovalInstanceCommandResult
    {
        $this->validateSystemCommand($instanceId, $commandId, $expectedVersion, $context);
        if (!Uuid::isValid($executionId)) {
            throw ApprovalFlowException::invalidExecutionId($executionId);
        }

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

    public function decide(string $instanceId, string $taskId, string $commandId, int $expectedVersion, ApprovalOutcome $outcome, string $reason, Context $context): ApprovalInstanceCommandResult
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || !$source->isAllowed(self::DECIDE_PRIVILEGE) || $source->getUserId() === null || $source->getIntegrationId() !== null) {
            throw ApprovalFlowException::missingPrivileges([self::DECIDE_PRIVILEGE]);
        }
        if ($expectedVersion < 1 || !Uuid::isValid($instanceId) || !Uuid::isValid($taskId) || !Uuid::isValid($commandId) || mb_strlen($reason) > 1000) {
            throw ApprovalFlowException::invalidInstanceCommand('Valid instance, task and command IDs, a positive version and a reason of at most 1000 characters are required.');
        }
        $actorJson = json_encode(['source' => 'admin-api', 'userId' => $source->getUserId(), 'integrationId' => null], \JSON_THROW_ON_ERROR);

        return $this->store->decide($instanceId, $taskId, $commandId, $expectedVersion, $outcome, $reason, $source->getUserId(), $actorJson, $context);
    }

    private function validateSystemCommand(string $instanceId, string $commandId, int $expectedVersion, Context $context): void
    {
        if (!$context->getSource() instanceof SystemSource) {
            throw ApprovalFlowException::missingPrivileges(['system']);
        }
        if ($expectedVersion < 1 || !Uuid::isValid($instanceId) || !Uuid::isValid($commandId)) {
            throw ApprovalFlowException::invalidInstanceCommand('A positive aggregate version and valid instance and command IDs are required.');
        }
    }
}
