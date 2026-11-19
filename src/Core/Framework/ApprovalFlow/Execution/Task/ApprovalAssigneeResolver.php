<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution\Task;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalNodeConfiguration;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * Resolves the published fixed assignees before any approval tasks are written.
 *
 * @internal
 */
#[Package('framework')]
class ApprovalAssigneeResolver
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return non-empty-list<string>
     */
    public function resolveAssignees(ApprovalNodeConfiguration $configuration): array
    {
        foreach ($configuration->userIds as $userId) {
            if (!Uuid::isValid($userId)) {
                throw ApprovalFlowException::assigneeUnavailable($userId);
            }
        }

        // Read current account status and hold the account rows until the instance command commits.
        $users = $this->connection->fetchAllKeyValue(
            'SELECT id, active FROM `user` WHERE id IN (:ids) ORDER BY id LOCK IN SHARE MODE',
            ['ids' => Uuid::fromHexToBytesList($configuration->userIds)],
            ['ids' => ArrayParameterType::BINARY],
        );
        foreach ($configuration->userIds as $userId) {
            if (!(bool) ($users[Uuid::fromHexToBytes($userId)] ?? false)) {
                throw ApprovalFlowException::assigneeUnavailable($userId);
            }
        }

        return $configuration->userIds;
    }
}
