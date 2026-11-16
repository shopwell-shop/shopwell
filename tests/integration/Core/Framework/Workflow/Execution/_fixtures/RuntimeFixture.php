<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\_fixtures;

use Doctrine\DBAL\Connection;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Publication\Approval\ApprovalWorkflowCompiler;
use Shopwell\Core\Framework\Workflow\Publication\Entity\WorkflowReleaseEntity;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
final class RuntimeFixture
{
    public readonly WorkflowReleaseEntity $release;

    public readonly Context $context;

    public readonly string $userId;

    private readonly string $avatarId;

    public function __construct(private readonly Connection $connection, WorkflowService $flows)
    {
        $user = TestUser::createNewTestUser($connection);
        $this->userId = $user->getUserId();
        $this->avatarId = $connection->fetchOne('SELECT avatar_id FROM user WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->userId)]);
        $source = new AdminApiSource($this->userId);
        $source->setPermissions(['workflow_instance:access']);
        $this->context = new Context($source);
        $raw = (string) file_get_contents(\dirname(__DIR__, 6) . '/unit/Core/Framework/Workflow/Publication/Approval/_fixtures/default-approval-request.json');
        $codec = new WorkflowDocumentCodec(Validation::createValidator());
        $graph = (new ApprovalWorkflowCompiler())->compile($codec->decode(str_replace('0123456789abcdef0123456789abcdef', $this->userId, $raw)));
        $definitionId = $flows->createDefinition(['code' => 'runtime-' . Uuid::randomHex(), 'name' => 'Runtime approval', 'graphData' => $graph], $this->context);
        $flows->publishDefinition($definitionId, $this->context);
        $this->release = new WorkflowReleaseEntity();
        $this->release->assign(['id' => $definitionId, 'publisher' => 'core:approval', 'revision' => 1]);
    }

    public function cleanup(): void
    {
        $definitionId = Uuid::fromHexToBytes($this->release->getId());
        $this->connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_task task ON history.referenced_id = task.id INNER JOIN workflow_instance instance ON instance.id = task.instance_id WHERE instance.definition_id = :id', ['id' => $definitionId]);
        $this->connection->executeStatement('DELETE history FROM state_machine_history history INNER JOIN workflow_instance instance ON history.referenced_id = instance.id WHERE instance.definition_id = :id', ['id' => $definitionId]);
        $this->connection->delete('workflow_instance', ['definition_id' => $definitionId]);
        $this->connection->delete('workflow_execution_definition', ['id' => $definitionId]);
        $this->connection->delete('user', ['id' => Uuid::fromHexToBytes($this->userId)]);
        $this->connection->delete('media', ['id' => $this->avatarId]);
    }
}
