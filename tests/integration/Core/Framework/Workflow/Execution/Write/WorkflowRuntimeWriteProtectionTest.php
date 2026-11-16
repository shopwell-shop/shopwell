<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowService;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Shopwell\Tests\Integration\Core\Framework\Workflow\Execution\_fixtures\RuntimeFixture;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowRuntimeWriteProtectionTest extends TestCase
{
    use KernelTestBehaviour;

    private RuntimeFixture $fixture;

    private string $instanceId;

    private WorkflowService $flows;

    protected function setUp(): void
    {
        $flows = static::getContainer()->get(WorkflowService::class);
        $connection = static::getContainer()->get(Connection::class);
        static::assertInstanceOf(WorkflowService::class, $flows);
        static::assertInstanceOf(Connection::class, $connection);
        $this->flows = $flows;
        $this->fixture = new RuntimeFixture($connection, $flows);
        $this->instanceId = $flows->start($this->fixture->release->getId(), [], 'Protected approval', $this->fixture->context);
    }

    protected function tearDown(): void
    {
        $this->fixture->cleanup();
    }

    public function testGenericStateTransitionCannotApproveABusinessWorkflow(): void
    {
        $registry = static::getContainer()->get(StateMachineRegistry::class);
        static::assertInstanceOf(StateMachineRegistry::class, $registry);
        $this->expectExceptionObject(WorkflowExecutionException::forbidden('Approval records can only be changed through approval actions.'));

        try {
            $registry->transition(
                new Transition(WorkflowInstanceEntity::ENTITY_NAME, $this->instanceId, 'agree', 'stateId'),
                $this->fixture->context,
            );
        } finally {
            static::assertTrue($this->flows->loadInstance($this->instanceId, $this->fixture->context)->isRunning());
        }
    }

    public function testGenericEntityWriteCannotReplaceTheInitiator(): void
    {
        $source = $this->fixture->context->getSource();
        static::assertInstanceOf(AdminApiSource::class, $source);
        $source->setIsAdmin(true);
        $repository = static::getContainer()->get('workflow_instance.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);
        $this->expectExceptionObject(WorkflowExecutionException::forbidden('Approval records can only be changed through approval actions.'));

        $repository->update([
            ['id' => $this->instanceId, 'initiatorId' => Uuid::randomHex()],
        ], $this->fixture->context);
    }
}
