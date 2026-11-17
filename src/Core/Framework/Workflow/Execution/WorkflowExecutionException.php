<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Workflow exceptions expose stable codes and standard HTTP error responses.
 *
 * @internal
 */
#[Package('framework')]
class WorkflowExecutionException extends WorkflowException
{
    public const INVALID_GRAPH = 'FRAMEWORK__WORKFLOW_EXECUTION_INVALID_GRAPH';
    public const UNKNOWN_NODE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNKNOWN_NODE';
    public const DEAD_END = 'FRAMEWORK__WORKFLOW_EXECUTION_DEAD_END';
    public const UNSUPPORTED_NODE_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_NODE_TYPE';
    public const UNSUPPORTED_COUNTERSIGN_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_COUNTERSIGN_TYPE';
    public const UNSUPPORTED_ASSIGNEE_TYPE = 'FRAMEWORK__WORKFLOW_EXECUTION_UNSUPPORTED_ASSIGNEE_TYPE';
    public const NO_ASSIGNEE = 'FRAMEWORK__WORKFLOW_EXECUTION_NO_ASSIGNEE';
    public const GRAPH_INVALID = 'FRAMEWORK__WORKFLOW_EXECUTION_GRAPH_INVALID';
    public const DEFINITION_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_DEFINITION_NOT_FOUND';
    public const DEFINITION_NOT_PUBLISHED = 'FRAMEWORK__WORKFLOW_EXECUTION_DEFINITION_NOT_PUBLISHED';
    public const INSTANCE_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_INSTANCE_NOT_FOUND';
    public const TASK_NOT_FOUND = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_NOT_FOUND';
    public const TASK_NOT_YOURS = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_NOT_YOURS';
    public const INSTANCE_NOT_RUNNING = 'FRAMEWORK__WORKFLOW_EXECUTION_INSTANCE_NOT_RUNNING';
    public const CONDITION_INVALID = 'FRAMEWORK__WORKFLOW_EXECUTION_CONDITION_INVALID';
    public const ACTOR_REQUIRED = 'FRAMEWORK__WORKFLOW_EXECUTION_ACTOR_REQUIRED';
    public const TASK_ALREADY_HANDLED = 'FRAMEWORK__WORKFLOW_EXECUTION_TASK_ALREADY_HANDLED';
    public const COMMENT_REQUIRED = 'FRAMEWORK__WORKFLOW_EXECUTION_COMMENT_REQUIRED';
    public const FORBIDDEN = 'FRAMEWORK__WORKFLOW_EXECUTION_FORBIDDEN';
    public const VALIDATION_FAILED = 'FRAMEWORK__WORKFLOW_EXECUTION_VALIDATION_FAILED';

    public static function validationFailed(\Throwable $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::VALIDATION_FAILED,
            'Workflow validation failed.',
            previous: $previous,
        );
    }

    public static function invalidGraph(string $reason, ?string $path = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_GRAPH,
            'Invalid workflow graph: {{ reason }}',
            $path === null ? ['reason' => $reason] : ['reason' => $reason, 'path' => $path]
        );
    }

    public static function unknownNode(string $nodeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNKNOWN_NODE,
            'Node "{{ nodeId }}" does not exist in the workflow graph.',
            ['nodeId' => $nodeId]
        );
    }

    public static function deadEnd(string $nodeId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::DEAD_END,
            'Node "{{ nodeId }}" has no eligible outgoing edge; the workflow cannot continue.',
            ['nodeId' => $nodeId]
        );
    }

    public static function unsupportedNodeType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_NODE_TYPE,
            'Unsupported node type "{{ type }}".',
            ['type' => $type]
        );
    }

    public static function unsupportedCountersignType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_COUNTERSIGN_TYPE,
            'Unsupported approval mode "{{ type }}".',
            ['type' => $type]
        );
    }

    public static function unsupportedAssigneeType(string $type): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::UNSUPPORTED_ASSIGNEE_TYPE,
            'Unsupported assignee type "{{ type }}".',
            ['type' => $type]
        );
    }

    /**
     * Fail immediately when no assignee resolves so an approval stage cannot be silently bypassed.
     */
    public static function noAssignee(string $nodeId, string $assigneeType): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_ASSIGNEE,
            'No assignee could be resolved for node "{{ nodeId }}" (type {{ assigneeType }}).',
            ['nodeId' => $nodeId, 'assigneeType' => $assigneeType]
        );
    }

    /**
     * @param list<array{code: string, message: string, nodeId?: string|null, edgeId?: string|null}> $violations
     */
    public static function graphInvalid(array $violations): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::GRAPH_INVALID,
            'Workflow graph validation failed: {{ message }}',
            [
                'message' => implode('; ', array_map(
                    static fn (array $v): string => $v['message'],
                    $violations
                )),
                'violations' => $violations,
            ]
        );
    }

    public static function definitionNotFound(string $definitionId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DEFINITION_NOT_FOUND,
            'Workflow definition "{{ definitionId }}" does not exist.',
            ['definitionId' => $definitionId]
        );
    }

    public static function definitionNotPublished(string $definitionId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::DEFINITION_NOT_PUBLISHED,
            'Workflow definition "{{ definitionId }}" must be published before it can be started.',
            ['definitionId' => $definitionId]
        );
    }

    public static function instanceNotFound(string $instanceId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::INSTANCE_NOT_FOUND,
            'Workflow instance "{{ instanceId }}" does not exist.',
            ['instanceId' => $instanceId]
        );
    }

    public static function taskNotFound(string $taskId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TASK_NOT_FOUND,
            'Workflow task "{{ taskId }}" does not exist.',
            ['taskId' => $taskId]
        );
    }

    /**
     * Return 404 for another user's task to avoid exposing its existence.
     */
    public static function taskNotYours(string $taskId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::TASK_NOT_YOURS,
            'Workflow task "{{ taskId }}" does not exist or does not belong to the current user.',
            ['taskId' => $taskId]
        );
    }

    public static function instanceNotRunning(string $instanceId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INSTANCE_NOT_RUNNING,
            'Workflow instance "{{ instanceId }}" is not running and cannot accept this operation.',
            ['instanceId' => $instanceId]
        );
    }

    public static function resubmissionConflict(string $instanceId): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_RESUBMISSION_CONFLICT', 'Workflow instance "{{ instanceId }}" is not awaiting changes in the expected round.', ['instanceId' => $instanceId]);
    }

    public static function conditionInvalid(string $expr, string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONDITION_INVALID,
            'Invalid condition expression "{{ expr }}": {{ reason }}',
            ['expr' => $expr, 'reason' => $reason]
        );
    }

    public static function actorRequired(): self
    {
        return new self(
            Response::HTTP_UNAUTHORIZED,
            self::ACTOR_REQUIRED,
            'The current request has no actor and cannot perform an approval action.',
            []
        );
    }

    /**
     * Expose a stable error code for duplicate or concurrent task decisions.
     */
    public static function taskAlreadyHandled(string $taskId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TASK_ALREADY_HANDLED,
            'Workflow task "{{ taskId }}" has already been processed.',
            ['taskId' => $taskId]
        );
    }

    /**
     * Rejection requires a comment explaining what the initiator needs to change.
     */
    public static function commentRequired(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::COMMENT_REQUIRED,
            'An approval comment is required.',
            []
        );
    }

    /**
     * The actor cannot view or act on this record.
     */
    public static function forbidden(string $reason): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::FORBIDDEN,
            'Operation forbidden: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function formConflict(): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_FORM_CONFLICT', 'The form has changed. Reload it before submitting your changes.');
    }

    public static function reminderRequiresCommit(): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_REMINDER_TRANSACTION_ACTIVE', 'Approval reminders must be requested after the caller transaction commits.');
    }

    public static function reminderUnavailable(): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_REMINDER_UNAVAILABLE', 'There are no pending approvers eligible for a reminder. Wait for the reminder cooldown or delivery retry.');
    }

    public static function duplicateRegistration(string $kind, string $name): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_EXECUTION_DUPLICATE_REGISTRATION', 'Workflow {{ kind }} "{{ name }}" is registered more than once.', ['kind' => $kind, 'name' => $name]);
    }

    public static function stateNotLoaded(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_EXECUTION_STATE_NOT_LOADED', 'Load the stateMachineState association before reading the workflow state.');
    }

    public static function invalidSignal(string $name): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_EXECUTION_INVALID_SIGNAL', 'The node is not waiting for signal "{{ name }}".', ['name' => $name]);
    }

    public static function executionNotWaiting(string $id): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_EXECUTION_NOT_WAITING', 'Node execution "{{ id }}" is not waiting.', ['id' => $id]);
    }
}
