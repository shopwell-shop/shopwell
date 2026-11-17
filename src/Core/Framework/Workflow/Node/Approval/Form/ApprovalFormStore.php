<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Form;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;

/**
 * Private append-only journal. No DAL entity or generic API exposes field values.
 * Writes run inside the approval operation's instance lock and transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormTest
 */
#[Package('framework')]
final readonly class ApprovalFormStore
{
    public function __construct(private Connection $connection, private ClockInterface $clock)
    {
    }

    public function version(string $instanceId, bool $forUpdate = false): int
    {
        $revision = $this->connection->fetchOne(
            'SELECT revision FROM workflow_approval_form_change WHERE instance_id = :id ORDER BY revision DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''),
            ['id' => Uuid::fromHexToBytes($instanceId)],
        );

        return $revision === false ? 0 : (int) $revision;
    }

    /**
     * @param array<string, array{before: mixed, after: mixed}> $changes
     */
    public function append(WorkflowInstanceEntity $instance, ?string $taskId, string $actorId, int $revision, array $changes): void
    {
        $this->connection->insert('workflow_approval_form_change', [
            'id' => Uuid::randomBytes(),
            'instance_id' => Uuid::fromHexToBytes($instance->id),
            'task_id' => $taskId === null ? null : Uuid::fromHexToBytes($taskId),
            'round' => $instance->round,
            'actor_id' => Uuid::fromHexToBytes($actorId),
            'revision' => $revision,
            'changes' => json_encode($changes, \JSON_THROW_ON_ERROR),
            'created_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(string $instanceId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) AS id, LOWER(HEX(task_id)) AS taskId, LOWER(HEX(actor_id)) AS actorId, `round`, revision, changes, created_at AS createdAt FROM workflow_approval_form_change WHERE instance_id = :id ORDER BY revision',
            ['id' => Uuid::fromHexToBytes($instanceId)],
        );
        foreach ($rows as &$row) {
            $row['revision'] = (int) $row['revision'];
            $row['round'] = (int) $row['round'];
            $row['createdAt'] = (new \DateTimeImmutable($row['createdAt'], new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
            $row['changes'] = json_decode($row['changes'], true, flags: \JSON_THROW_ON_ERROR);
        }
        unset($row);

        return $rows;
    }
}
