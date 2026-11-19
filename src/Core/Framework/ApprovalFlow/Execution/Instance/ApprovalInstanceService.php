<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Instance;

use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Context\SystemSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Administrative suspension only; instance creation, activation and resumption require separate admission.
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

    public function __construct(private readonly ApprovalInstanceStore $store)
    {
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
}
