<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Api;

use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresentationEvent;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowInstancePresenter;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowUserLookup;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowLogCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Node\WorkflowNodeExecutionEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStates;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowInstancePresenter::class)]
class WorkflowInstancePresenterTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $inputSchema
     */
    #[DataProvider('frozenInputSchemas')]
    public function testGenericDetailUsesFrozenInputAndActualActivations(array $inputSchema): void
    {
        $instance = $this->instance();
        $instance->graphSnapshot = ['nodes' => [
            ['id' => 'start', 'type' => 'snaker:start', 'properties' => ['inputSchema' => $inputSchema]],
            ['id' => 'wait', 'type' => 'example:wait'],
            ['id' => 'end', 'type' => 'snaker:end'],
        ], 'edges' => [
            ['id' => 'start-wait', 'sourceNodeId' => 'start', 'targetNodeId' => 'wait'],
            ['id' => 'wait-end', 'sourceNodeId' => 'wait', 'targetNodeId' => 'end'],
        ]];
        $instance->currentNodeId = 'wait';
        $start = $this->execution(nodeId: 'start', sequence: 1, status: WorkflowNodeExecutionEntity::COMPLETED);
        $wait = $this->execution(nodeId: 'wait', sequence: 2, status: WorkflowNodeExecutionEntity::WAITING);
        $wait->sourceConnectionId = 'start-wait';
        $wait->waitingFor = 'example:resume';
        $wait->output = ['ticketId' => 'ticket'];
        $definitions = StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class, []);
        $events = new EventDispatcher();
        $event = null;
        $events->addListener(WorkflowInstancePresentationEvent::class, static function (WorkflowInstancePresentationEvent $dispatched) use (&$event): void {
            $event = $dispatched;
            $dispatched->addData(['exampleDetails' => ['ticketId' => 'ticket']]);
        });
        $presenter = new WorkflowInstancePresenter(
            new WorkflowUserLookup(new StaticEntityRepository([new UserCollection()])),
            $definitions,
            new StaticEntityRepository([new WorkflowNodeExecutionCollection([$start, $wait])]),
            $events,
            StaticEntityRepository::of(WorkflowLogCollection::class, [new WorkflowLogCollection()]),
        );

        $detail = $presenter->present($instance, Context::createDefaultContext(), withDetail: true);

        static::assertNotNull($event);
        static::assertTrue($event->withDetail);
        static::assertSame($instance, $event->instance);
        static::assertSame($inputSchema, $detail['formSchema']);
        static::assertSame([], $definitions->searchedCriteria);
        static::assertSame(['nodeIds' => ['start', 'wait'], 'edgeIds' => ['start-wait']], $detail['selectedPath']);
        static::assertSame(['ticketId' => 'ticket'], $detail['exampleDetails']);
        static::assertCount(2, $detail['executions']);
        static::assertSame('example:resume', $detail['executions'][1]['waitingFor']);
        static::assertSame(['ticketId' => 'ticket'], $detail['executions'][1]['output']);
        static::assertSame([], $detail['executions'][0]['output']);
        static::assertNull($detail['result']);
        static::assertArrayNotHasKey('approvalTasks', $detail);
        $this->assertResponseSchema($detail, 'WorkflowExecutionInstanceDetail');
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function frozenInputSchemas(): iterable
    {
        yield 'frozen fields do not read a mutable definition' => [[['name' => 'original']]];
        yield 'an explicitly empty frozen schema does not fall back' => [[]];
    }

    public function testLegacyInputSchemaFallsBackToDefinition(): void
    {
        $instance = $this->instance();
        $definition = new WorkflowExecutionDefinitionEntity();
        $definition->id = $instance->definitionId;
        $definition->setUniqueIdentifier($definition->id);
        $definition->formSchema = [['name' => 'legacy']];
        $presenter = new WorkflowInstancePresenter(
            new WorkflowUserLookup(new StaticEntityRepository([new UserCollection()])),
            new StaticEntityRepository([new WorkflowExecutionDefinitionCollection([$definition])]),
            new StaticEntityRepository([new WorkflowNodeExecutionCollection()]),
            new EventDispatcher(),
            new StaticEntityRepository([new WorkflowLogCollection()]),
        );

        $detail = $presenter->present($instance, Context::createDefaultContext(), withDetail: true);

        static::assertSame([['name' => 'legacy']], $detail['formSchema']);
        static::assertSame(['nodeIds' => [], 'edgeIds' => []], $detail['selectedPath']);
    }

    public function testSummaryDoesNotLoadExecutionDetails(): void
    {
        $presenter = new WorkflowInstancePresenter(
            new WorkflowUserLookup(new StaticEntityRepository([new UserCollection()])),
            StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class, []),
            StaticEntityRepository::of(WorkflowNodeExecutionCollection::class, []),
            new EventDispatcher(),
            StaticEntityRepository::of(WorkflowLogCollection::class, []),
        );

        $summary = $presenter->present($this->instance(), Context::createDefaultContext());

        static::assertArrayNotHasKey('executions', $summary);
        $this->assertResponseSchema($summary, 'WorkflowExecutionInstance');
    }

    public function testSummariesLoadInitiatorNamesOnceForTheWholePage(): void
    {
        $initiator = new UserEntity();
        $initiator->setId('initiator');
        $initiator->setName('First User');
        $users = new StaticEntityRepository([new UserCollection([$initiator])]);
        $presenter = new WorkflowInstancePresenter(
            new WorkflowUserLookup($users),
            StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class, []),
            StaticEntityRepository::of(WorkflowNodeExecutionCollection::class, []),
            new EventDispatcher(),
            StaticEntityRepository::of(WorkflowLogCollection::class, []),
        );
        $first = $this->instance();
        $second = $this->instance();
        $second->id = 'second-instance';

        $summaries = $presenter->presentMany([$first, $second], Context::createDefaultContext());

        static::assertCount(1, $users->searchedCriteria);
        static::assertSame(['instance', 'second-instance'], array_column($summaries, 'id'));
        static::assertSame([['id' => 'initiator', 'name' => 'First User'], ['id' => 'initiator', 'name' => 'First User']], array_column($summaries, 'initiator'));
    }

    public function testEmptyPageDoesNotQueryUsersOrExecutionDetails(): void
    {
        $users = StaticEntityRepository::of(UserCollection::class, []);
        $presenter = new WorkflowInstancePresenter(
            new WorkflowUserLookup($users),
            StaticEntityRepository::of(WorkflowExecutionDefinitionCollection::class, []),
            StaticEntityRepository::of(WorkflowNodeExecutionCollection::class, []),
            new EventDispatcher(),
            StaticEntityRepository::of(WorkflowLogCollection::class, []),
        );

        static::assertSame([], $presenter->presentMany([], Context::createDefaultContext()));
        static::assertSame([], $users->searchedCriteria);
    }

    private function instance(): WorkflowInstanceEntity
    {
        $state = new StateMachineStateEntity();
        $state->setTechnicalName(WorkflowInstanceStates::RUNNING);
        $instance = new WorkflowInstanceEntity();
        $instance->id = 'instance';
        $instance->number = 'WF-1';
        $instance->title = 'Generic workflow';
        $instance->definitionId = 'definition';
        $instance->definitionVersion = 1;
        $instance->initiatorId = 'initiator';
        $instance->stateMachineState = $state;
        $instance->graphSnapshot = ['nodes' => [['id' => 'start', 'type' => 'snaker:start']], 'edges' => []];

        return $instance;
    }

    private function execution(string $nodeId, int $sequence, string $status): WorkflowNodeExecutionEntity
    {
        $execution = new WorkflowNodeExecutionEntity();
        $execution->id = $nodeId;
        $execution->setUniqueIdentifier($execution->id);
        $execution->nodeId = $nodeId;
        $execution->sequence = $sequence;
        $execution->round = 1;
        $execution->status = $status;

        return $execution;
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertResponseSchema(array $response, string $component): void
    {
        $schemaFile = __DIR__ . '/../../../../../../../src/Core/Framework/Api/ApiDefinition/Generator/Schema/AdminApi/paths/workflow-execution.json';
        $schema = json_decode((string) file_get_contents($schemaFile), associative: true, flags: \JSON_THROW_ON_ERROR);
        $schema['$ref'] = '#/components/schemas/' . $component;
        $result = (new Validator())->validate(
            json_decode(json_encode($response, \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR),
            json_decode(json_encode($schema, \JSON_THROW_ON_ERROR), flags: \JSON_THROW_ON_ERROR),
        );

        static::assertTrue($result->isValid(), (string) $result->error()?->keyword());
    }
}
