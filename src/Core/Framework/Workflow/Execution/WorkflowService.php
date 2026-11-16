<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionCollection;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionStatus;
use Shopwell\Core\Framework\Workflow\Execution\Event\WorkflowStartedEvent;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Log\WorkflowRecorder;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\WorkflowGraphValidator;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceStateHandler;

/**
 * @internal Generic instance lifecycle; node behavior is registered independently.
 */
#[Package('framework')]
final readonly class WorkflowService
{
    /**
     * @param EntityRepository<WorkflowExecutionDefinitionCollection> $definitionRepository
     * @param EntityRepository<WorkflowInstanceCollection> $instanceRepository
     */
    public function __construct(
        private EntityRepository $definitionRepository,
        private EntityRepository $instanceRepository,
        private WorkflowEngine $engine,
        private WorkflowGraphValidator $validator,
        private WorkflowTransactionRunner $transactions,
        private WorkflowEventQueue $events,
        private WorkflowRecorder $recorder,
        private WorkflowNumberGenerator $numbers,
        private WorkflowInstanceStateHandler $instanceStates,
        private WorkflowInputSchema $inputs,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDefinition(array $data, Context $context): string
    {
        $graphData = $this->normalizeGraph($data['graphData'] ?? self::initialGraph());
        $formSchema = $this->normalizeFormSchema($data['formSchema'] ?? null);

        $id = Uuid::randomHex();

        // Only this protected write needs system scope; inputs have already been normalized.
        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($id, $data, $graphData, $formSchema): void {
            $this->definitionRepository->create([
                [
                    'id' => $id,
                    'code' => (string) ($data['code'] ?? ''),
                    'name' => (string) ($data['name'] ?? ''),
                    'description' => isset($data['description']) ? (string) $data['description'] : null,
                    'status' => WorkflowExecutionDefinitionStatus::DRAFT,
                    'version' => 1,
                    'isLatest' => true,
                    'graphData' => $graphData,
                    'formSchema' => $formSchema,
                ],
            ], $scoped);
        });

        return $id;
    }

    /**
     * @param array<string, mixed> $graphData
     * @param list<array<string, mixed>>|null $formSchema
     */
    public function saveDefinition(string $definitionId, array $graphData, ?array $formSchema, Context $context): void
    {
        $definition = $this->loadDefinition($definitionId, $context);
        $graph = GraphData::fromArray($this->normalizeGraph($graphData));
        $schema = $formSchema === null ? $definition->formFields() : $this->normalizeFormSchema($formSchema);

        $this->assertGraphIsValid($graph, $schema);

        $this->definitionRepository->update([
            [
                'id' => $definitionId,
                'graphData' => $graph->toArray(),
                'formSchema' => $schema,
            ],
        ], $context);
    }

    public function publishDefinition(string $definitionId, Context $context): void
    {
        $definition = $this->loadDefinition($definitionId, $context);

        $graph = GraphData::fromArray($definition->graph());
        $this->assertGraphIsValid($graph, $definition->formFields());

        $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($definitionId): void {
            $this->definitionRepository->update([
                ['id' => $definitionId, 'status' => WorkflowExecutionDefinitionStatus::PUBLISHED],
            ], $scoped);
        });
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function start(string $definitionId, array $formData, string $title, Context $context): string
    {
        $actorId = $this->actorId($context);
        $definition = $this->loadDefinition($definitionId, $context);

        if ($definition->status !== WorkflowExecutionDefinitionStatus::PUBLISHED) {
            throw WorkflowExecutionException::definitionNotPublished($definitionId);
        }

        $graph = GraphData::fromArray($definition->graph());
        $this->assertGraphIsValid($graph, $definition->formFields());
        $schema = $graph->singleStartNode()->property('inputSchema');

        if (\is_array($schema)) {
            $formData = $this->inputs->normalizeData($schema, $formData, $context);
        } else {
            $this->assertFormDataIsComplete($definition->formFields(), $formData);
        }

        $instanceId = $this->transactions->run(
            fn (): string => $context->scope(
                Context::SYSTEM_SCOPE,
                function (Context $scoped) use ($definition, $graph, $formData, $title, $actorId): string {
                    $instanceId = Uuid::randomHex();

                    $this->instanceRepository->create([
                        [
                            'id' => $instanceId,
                            'number' => $this->numbers->next(),
                            'definitionId' => $definition->id,
                            'definitionVersion' => $definition->version,
                            'title' => $title !== '' ? $title : $definition->name,
                            'initiatorId' => $actorId,
                            'formData' => $formData,
                            // GraphData has already removed fields outside the snapshot contract.
                            'graphSnapshot' => $graph->toArray(),
                            'stateId' => $this->instanceStates->initialStateId($scoped),
                            'round' => 1,
                            'startedAt' => $this->clock->now(),
                        ],
                    ], $scoped);

                    $instance = $this->loadInstance($instanceId, $scoped);

                    $execution = new WorkflowExecutionContext($graph, $instance, $scoped, $actorId, $instance->round);

                    $this->engine->start($instance, $execution);

                    $this->recorder->record($instanceId, 'start', null, $actorId, null, [
                        'definitionId' => $definition->id,
                        'version' => $definition->version,
                    ], $scoped);

                    $this->events->push(new WorkflowStartedEvent(
                        $instanceId,
                        $definition->id,
                        $actorId,
                        $instance->title
                    ));

                    return $instanceId;
                }
            )
        );

        $this->events->flush();

        return $instanceId;
    }

    public function loadDefinition(string $definitionId, Context $context): WorkflowExecutionDefinitionEntity
    {
        /** @var WorkflowExecutionDefinitionEntity|null $definition */
        $definition = $this->definitionRepository
            ->search(new Criteria([$definitionId]), $context)
            ->getEntities()
            ->first();

        return $definition ?? throw WorkflowExecutionException::definitionNotFound($definitionId);
    }

    public function loadInstance(string $instanceId, Context $context): WorkflowInstanceEntity
    {
        /** @var WorkflowInstanceEntity|null $instance */
        $instance = $this->instanceRepository
            ->search((new Criteria([$instanceId]))->addAssociation('stateMachineState'), $context)
            ->getEntities()
            ->first();

        return $instance ?? throw WorkflowExecutionException::instanceNotFound($instanceId);
    }

    public function actorId(Context $context): string
    {
        $source = $context->getSource();

        if (!$source instanceof AdminApiSource) {
            throw WorkflowExecutionException::actorRequired();
        }

        return $source->getUserId() ?? throw WorkflowExecutionException::actorRequired();
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>}
     */
    public static function initialGraph(): array
    {
        return [
            'nodes' => [
                ['id' => 'n_start', 'type' => WorkflowNodeTypes::START, 'x' => 0, 'y' => 0, 'properties' => ['name' => '发起人']],
                ['id' => 'n_end', 'type' => WorkflowNodeTypes::END, 'x' => 0, 'y' => 64, 'properties' => ['name' => '结束']],
            ],
            'edges' => [
                ['id' => 'e_start_end', 'sourceNodeId' => 'n_start', 'targetNodeId' => 'n_end', 'properties' => []],
            ],
        ];
    }

    public function signal(string $instanceId, string $executionId, WorkflowSignal $signal, Context $context): void
    {
        if ($signal->name === WorkflowSignal::TASK_COMPLETED) {
            throw WorkflowExecutionException::forbidden('Approval tasks must be completed through approval actions.');
        }

        $this->transactions->run(function () use ($instanceId, $executionId, $signal, $context): void {
            $this->transactions->lockInstance($instanceId);
            $instance = $this->loadInstance($instanceId, $context);

            if (!$instance->isRunning()) {
                throw WorkflowExecutionException::instanceNotRunning($instanceId);
            }

            $source = $context->getSource();
            $actorId = $source instanceof AdminApiSource ? $source->getUserId() : null;
            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($instance, $executionId, $signal, $actorId): void {
                $execution = new WorkflowExecutionContext(GraphData::fromArray($instance->snapshot()), $instance, $scoped, $actorId, $instance->round);
                $this->engine->resume($executionId, $signal, $execution);
            });
        });
        $this->events->flush();
    }

    public function cancel(string $instanceId, string $reason, Context $context): void
    {
        $actorId = $this->actorId($context);
        $this->transactions->run(function () use ($instanceId, $reason, $context, $actorId): void {
            $this->transactions->lockInstance($instanceId);
            $instance = $this->loadInstance($instanceId, $context);

            if ($instance->initiatorId !== $actorId) {
                throw WorkflowExecutionException::forbidden('Only the initiator can cancel this workflow.');
            }

            if (!$instance->isRunning()) {
                throw WorkflowExecutionException::instanceNotRunning($instanceId);
            }

            $context->scope(Context::SYSTEM_SCOPE, function (Context $scoped) use ($instance, $reason, $actorId): void {
                $execution = new WorkflowExecutionContext(GraphData::fromArray($instance->snapshot()), $instance, $scoped, $actorId, $instance->round);
                $this->engine->cancel($execution);
                $this->instanceStates->cancel($instance->id, $scoped);
                $this->recorder->record($instance->id, 'cancel', null, $actorId, $reason, [], $scoped);
            });
        });
        $this->events->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeGraph(mixed $graphData): array
    {
        if (!\is_array($graphData)) {
            throw WorkflowExecutionException::invalidGraph('graphData is required.');
        }

        return $graphData;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeFormSchema(mixed $formSchema): array
    {
        if ($formSchema === null) {
            return [];
        }

        if (!\is_array($formSchema)) {
            throw WorkflowExecutionException::invalidGraph('formSchema must be an array.');
        }

        return array_values(array_filter($formSchema, 'is_array'));
    }

    /**
     * @param list<array<string, mixed>> $formSchema
     */
    private function assertGraphIsValid(GraphData $graph, array $formSchema): void
    {
        $violations = $this->validator->validate($graph, ValidationContext::fromFormSchema($formSchema));

        if (!$this->validator->hasErrors($violations)) {
            return;
        }

        throw WorkflowExecutionException::graphInvalid(array_map(
            static fn ($violation): array => [
                'code' => $violation->code,
                'message' => $violation->message,
                'nodeId' => $violation->nodeId,
                'edgeId' => $violation->edgeId,
            ],
            $violations
        ));
    }

    /**
     * @param list<array<string, mixed>> $formSchema
     * @param array<string, mixed> $formData
     */
    private function assertFormDataIsComplete(array $formSchema, array $formData): void
    {
        $missing = [];

        foreach ($formSchema as $field) {
            if (($field['required'] ?? false) !== true) {
                continue;
            }

            $key = $field['key'] ?? null;

            if (!\is_string($key) || $key === '') {
                continue;
            }

            if (!\array_key_exists($key, $formData) || $formData[$key] === null || $formData[$key] === '') {
                $missing[] = \is_string($field['label'] ?? null) ? $field['label'] : $key;
            }
        }

        if ($missing !== []) {
            throw WorkflowExecutionException::invalidGraph('Required fields: ' . implode(', ', $missing));
        }
    }
}
