<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Form;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceCollection;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\Framework\Workflow\Task\WorkflowTaskEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Approval-owned form changes. Caller owns task authorization, instance locking and transaction.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Form\ApprovalFormTest
 */
#[Package('framework')]
final readonly class ApprovalFormService
{
    public const VERSION_KEY = 'approvalFormVersion';

    /**
     * @param EntityRepository<WorkflowInstanceCollection> $instances
     */
    public function __construct(
        private ApprovalFormPolicy $policy,
        private WorkflowInputSchema $inputs,
        private ApprovalFormStore $store,
        private EntityRepository $instances,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function schema(WorkflowInstanceEntity $instance): array
    {
        return $this->inputs->normalize(GraphData::fromArray($instance->snapshot())->singleStartNode()->property('inputSchema', []));
    }

    /**
     * @return array<string, array{access: string, required: bool}>
     */
    public function permissions(WorkflowInstanceEntity $instance, ?string $nodeId, string $actorId, Context $context, bool $initiator = false): array
    {
        $schema = $this->schema($instance);
        $configuration = $nodeId === null ? [] : GraphData::fromArray($instance->snapshot())->node($nodeId)->property('approvalFormPermissions', []);
        if ($initiator) {
            $configuration = [];
            foreach ($schema as $field) {
                $configuration[$field['key']] = ['access' => ApprovalFormPolicy::EDITABLE, 'required' => $field['required']];
            }
        }
        $permissions = $this->policy->normalize($configuration, $schema);
        $event = new ApprovalFormPolicyEvent($instance, $nodeId, $actorId, $context);
        $this->dispatcher->dispatch($event);

        return $event->restrict($permissions);
    }

    /**
     * @param array<string, mixed> $patch
     */
    public function approve(WorkflowTaskEntity $task, WorkflowExecutionContext $execution, array $patch, ?int $version): WorkflowExecutionContext
    {
        $instance = $execution->instance();
        $permissions = $this->permissions($instance, $task->nodeId, $task->assigneeId, $execution->context());
        $data = $this->merge($instance, $permissions, $patch, $version, $execution->context(), $task->id, $task->assigneeId);
        $fresh = clone $instance;
        $fresh->formData = $data;
        $fresh->ext = [...($instance->ext ?? []), self::VERSION_KEY => $this->store->version($instance->id, forUpdate: true)];

        return $execution->withInstance($fresh);
    }

    /**
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    public function resubmit(WorkflowInstanceEntity $instance, array $patch, ?int $version, string $actorId, Context $context): array
    {
        return $this->merge($instance, $this->permissions($instance, null, $actorId, $context, initiator: true), $patch, $version, $context, null, $actorId);
    }

    /**
     * @param array<string, array{access: string, required: bool}> $permissions
     * @param array<string, mixed> $patch
     *
     * @return array<string, mixed>
     */
    private function merge(WorkflowInstanceEntity $instance, array $permissions, array $patch, ?int $version, Context $context, ?string $taskId, string $actorId): array
    {
        $currentVersion = $this->store->version($instance->id, forUpdate: true);
        if (($instance->ext[self::VERSION_KEY] ?? 0) !== $currentVersion
            || ($patch !== [] && $version === null)
            || ($version !== null && $version !== $currentVersion)) {
            throw WorkflowExecutionException::formConflict();
        }

        foreach (array_keys($patch) as $key) {
            if (($permissions[$key]['access'] ?? null) !== ApprovalFormPolicy::EDITABLE) {
                throw WorkflowExecutionException::forbidden('The field is not editable in this form context: ' . $key);
            }
        }

        // Validate only submitted fields. Frozen evidence must not depend on today's option catalogue.
        $schema = $this->schema($instance);
        $patchSchema = [];
        foreach ($schema as $field) {
            if (\array_key_exists($field['key'], $patch)) {
                $field['required'] = false;
                $patchSchema[] = $field;
            }
        }
        $normalized = $this->inputs->normalizeData($patchSchema, $patch, $context);
        $data = array_replace($instance->form(), $normalized);
        foreach ($permissions as $key => $permission) {
            $value = $data[$key] ?? null;
            if ($permission['required'] && ($value === null || $value === [] || (\is_string($value) && trim($value) === ''))) {
                throw WorkflowExecutionException::invalidGraph('A value is required for input field: ' . $key);
            }
        }

        $changes = [];
        foreach ($normalized as $key => $value) {
            $before = $instance->form()[$key] ?? null;
            if ($before !== $value) {
                $changes[$key] = ['before' => $before, 'after' => $value];
            }
        }
        if ($changes !== []) {
            $this->store->append($instance, $taskId, $actorId, $currentVersion + 1, $changes);
            $this->instances->update([[
                'id' => $instance->id,
                'formData' => $data,
                'ext' => [...($instance->ext ?? []), self::VERSION_KEY => $currentVersion + 1],
            ]], $context);
        }

        return $data;
    }
}
