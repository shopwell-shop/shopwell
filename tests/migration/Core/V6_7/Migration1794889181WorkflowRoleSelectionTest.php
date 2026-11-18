<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Migration\V6_7\Migration1794889181WorkflowRoleSelection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1794889181WorkflowRoleSelection::class)]
class Migration1794889181WorkflowRoleSelectionTest extends TestCase
{
    public function testExistingWorkflowViewersGainOnlyRoleReadAccessIdempotently(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $viewer = Uuid::randomBytes();
        $participant = Uuid::randomBytes();
        $connection->beginTransaction();
        try {
            foreach ([$viewer => ['workflow.viewer', 'user:read'], $participant => ['workflow_participant.viewer']] as $id => $privileges) {
                $connection->insert('acl_role', [
                    'id' => $id, 'name' => 'Workflow test role',
                    'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR), 'created_at' => '2026-11-17 00:00:00',
                ]);
            }
            $migration = new Migration1794889181WorkflowRoleSelection();
            $migration->update($connection);
            $migration->update($connection);

            static::assertSame(['workflow.viewer', 'user:read', 'acl_role:read'], json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$viewer]), true, 512, \JSON_THROW_ON_ERROR));
            static::assertSame(['workflow_participant.viewer'], json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$participant]), true, 512, \JSON_THROW_ON_ERROR));
            static::assertSame(1794889181, $migration->getCreationTimestamp());
        } finally {
            $connection->rollBack();
        }
    }
}
