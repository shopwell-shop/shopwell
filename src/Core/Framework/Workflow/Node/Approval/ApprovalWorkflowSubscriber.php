<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowAssigneeSources;
use Shopwell\Core\Framework\Workflow\Definition\Type\WorkflowNodeTypes as DocumentNodeTypes;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Event\WorkflowValidationEvent;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal Definition validation only; this subscriber does not resolve users or execute approvals.
 */
#[Package('framework')]
final class ApprovalWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ApprovalModeRegistry $modes)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [WorkflowValidationEvent::class => 'validate'];
    }

    public function validate(WorkflowValidationEvent $event): void
    {
        foreach ($event->document->nodes as $index => $node) {
            if ($node->type !== DocumentNodeTypes::APPROVAL || $node->typeVersion !== 1) {
                continue;
            }

            $config = $this->objectMap($node->config);
            $path = '/nodes/' . $index . '/config';
            $assigneeViolation = $this->validateAssignee($config['assignee'] ?? null, $path . '/assignee', $node->id);
            if ($assigneeViolation !== null) {
                $event->addViolation($assigneeViolation);
            }

            $approval = $this->metadata($config['approval'] ?? null, 'mode');
            if ($approval === null) {
                $event->addViolation(new WorkflowViolation('approval_invalid_configuration', $path . '/approval', $node->id));

                continue;
            }

            $version = \array_key_exists('version', $approval) ? $approval['version'] : 1;
            if (!\is_int($version) || $version < 1) {
                $event->addViolation(new WorkflowViolation('approval_invalid_configuration', $path . '/approval', $node->id));

                continue;
            }

            try {
                $mode = $this->modes->get($approval['mode'], $version);
            } catch (WorkflowException) {
                // Preserve unavailable extensions in drafts; publication requires the implementation.
                continue;
            }

            try {
                $mode->validateConfiguration($approval['config']);
            } catch (WorkflowException) {
                $event->addViolation(new WorkflowViolation('approval_invalid_configuration', $path . '/approval', $node->id));

                continue;
            }

            $assignee = $this->metadata($config['assignee'] ?? null, 'source');
            if ($assigneeViolation !== null || $assignee === null) {
                continue;
            }

            $multiple = match ($assignee['source']) {
                WorkflowAssigneeSources::USER => false,
                WorkflowAssigneeSources::USERS, WorkflowAssigneeSources::DEPARTMENT_LEADERS => true,
                default => null,
            };

            if ($multiple !== null && $multiple !== $mode->supportsMultipleAssignees()) {
                $event->addViolation(new WorkflowViolation('approval_incompatible_mode', $path . '/approval/mode', $node->id));
            }
        }
    }

    private function validateAssignee(mixed $value, string $path, string $nodeId): ?WorkflowViolation
    {
        if ($value === null) {
            return new WorkflowViolation('approval_assignee_required', $path, $nodeId);
        }

        $assignee = $this->metadata($value, 'source');
        if ($assignee === null) {
            return new WorkflowViolation('approval_invalid_configuration', $path, $nodeId);
        }

        if ($assignee['source'] === WorkflowAssigneeSources::DEPARTMENT_LEADERS) {
            return $assignee['config'] === [] ? null : new WorkflowViolation('approval_invalid_configuration', $path . '/config', $nodeId);
        }

        if ($assignee['source'] === WorkflowAssigneeSources::USERS) {
            return $this->validateUserIds($assignee['config']['userIds'] ?? null, $path . '/config/userIds', $nodeId);
        }

        if ($assignee['source'] !== WorkflowAssigneeSources::USER) {
            return null;
        }

        $userId = $assignee['config']['userId'] ?? null;
        if ($userId === null || $userId === '') {
            return new WorkflowViolation('approval_assignee_required', $path . '/config/userId', $nodeId);
        }

        if (!\is_string($userId) || !Uuid::isValid($userId)) {
            return new WorkflowViolation('approval_invalid_configuration', $path . '/config/userId', $nodeId);
        }

        return null;
    }

    private function validateUserIds(mixed $userIds, string $path, string $nodeId): ?WorkflowViolation
    {
        if ($userIds === null || $userIds === []) {
            return new WorkflowViolation('approval_assignee_required', $path, $nodeId);
        }

        if (!\is_array($userIds) || !array_is_list($userIds)) {
            return new WorkflowViolation('approval_invalid_configuration', $path, $nodeId);
        }

        $seen = [];
        foreach ($userIds as $userId) {
            if (!\is_string($userId) || !Uuid::isValid(strtolower($userId)) || isset($seen[strtolower($userId)])) {
                return new WorkflowViolation('approval_invalid_configuration', $path, $nodeId);
            }

            $seen[strtolower($userId)] = true;
        }

        return null;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function metadata(mixed $value, string $name): ?array
    {
        $metadata = $this->objectMap($value);
        if ($metadata === null || !\is_string($metadata[$name] ?? null)
            || !preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $metadata[$name])) {
            return null;
        }

        $config = $this->objectMap($metadata['config'] ?? null);
        if ($config === null) {
            return null;
        }

        return [...$metadata, 'config' => $config];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function objectMap(mixed $value): ?array
    {
        if ($value instanceof \stdClass) {
            return get_object_vars($value);
        }

        return \is_array($value) && ($value === [] || !array_is_list($value)) ? $value : null;
    }
}
