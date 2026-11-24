<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ApprovalFlowException extends HttpException
{
    public const INVALID_REQUEST_QUERY = 'APPROVAL_FLOW__INVALID_REQUEST_QUERY';

    public const RESULT_HANDLER_NOT_FOUND = 'APPROVAL_FLOW__RESULT_HANDLER_NOT_FOUND';
    public const DUPLICATE_RESULT_HANDLER = 'APPROVAL_FLOW__DUPLICATE_RESULT_HANDLER';
    public const RESULT_REQUEST_NOT_FOUND = 'APPROVAL_FLOW__RESULT_REQUEST_NOT_FOUND';

    public const REQUEST_NOT_FOUND = 'APPROVAL_FLOW__REQUEST_NOT_FOUND';
    public const INSTANCE_COMMAND_IN_PROGRESS = 'APPROVAL_FLOW__INSTANCE_COMMAND_IN_PROGRESS';
    public const REQUEST_ACTOR_REQUIRED = 'APPROVAL_FLOW__REQUEST_ACTOR_REQUIRED';
    public const REQUEST_INITIATOR_REQUIRED = 'APPROVAL_FLOW__REQUEST_INITIATOR_REQUIRED';
    public const INVALID_REQUEST_FORM = 'APPROVAL_FLOW__INVALID_REQUEST_FORM';
    public const REQUEST_TRANSACTION_REQUIRED = 'APPROVAL_FLOW__REQUEST_TRANSACTION_REQUIRED';

    public const BINDING_WRITE_IN_PROGRESS = 'APPROVAL_FLOW__BINDING_WRITE_IN_PROGRESS';
    public const BINDING_NOT_FOUND = 'APPROVAL_FLOW__BINDING_NOT_FOUND';
    public const ACTIVE_BINDING_NOT_FOUND = 'APPROVAL_FLOW__ACTIVE_BINDING_NOT_FOUND';
    public const BINDING_REVISION_CONFLICT = 'APPROVAL_FLOW__BINDING_REVISION_CONFLICT';
    public const BINDING_VERSION_CONFLICT = 'APPROVAL_FLOW__BINDING_VERSION_CONFLICT';
    public const BINDING_RELEASE_MISMATCH = 'APPROVAL_FLOW__BINDING_RELEASE_MISMATCH';
    public const BINDING_FORM_CHANGED = 'APPROVAL_FLOW__BINDING_FORM_CHANGED';
    public const UNCONTROLLED_BINDING_WRITE = 'APPROVAL_FLOW__UNCONTROLLED_BINDING_WRITE';
    public const START_NODE_ALREADY_ENTERED = 'APPROVAL_FLOW__START_NODE_ALREADY_ENTERED';
    public const EXECUTION_NOT_FOUND = 'APPROVAL_FLOW__EXECUTION_NOT_FOUND';
    public const EXECUTION_OUTSIDE_SNAPSHOT = 'APPROVAL_FLOW__EXECUTION_OUTSIDE_SNAPSHOT';
    public const INVALID_APPROVAL_ENTRY = 'APPROVAL_FLOW__INVALID_APPROVAL_ENTRY';
    public const ASSIGNEE_UNAVAILABLE = 'APPROVAL_FLOW__ASSIGNEE_UNAVAILABLE';
    public const EMPTY_APPROVAL_ASSIGNEES = 'APPROVAL_FLOW__EMPTY_APPROVAL_ASSIGNEES';
    public const TASK_NOT_FOUND = 'APPROVAL_FLOW__TASK_NOT_FOUND';
    public const TASK_NOT_PENDING = 'APPROVAL_FLOW__TASK_NOT_PENDING';
    public const TASK_ASSIGNEE_REQUIRED = 'APPROVAL_FLOW__TASK_ASSIGNEE_REQUIRED';
    public const TASK_OUTSIDE_SNAPSHOT = 'APPROVAL_FLOW__TASK_OUTSIDE_SNAPSHOT';
    public const SINGLE_APPROVAL_ASSIGNEE_REQUIRED = 'APPROVAL_FLOW__SINGLE_APPROVAL_ASSIGNEE_REQUIRED';
    public const UNSUPPORTED_START_ROUTE = 'APPROVAL_FLOW__UNSUPPORTED_START_ROUTE';
    public const UNSUPPORTED_NODE_ROUTE = 'APPROVAL_FLOW__UNSUPPORTED_NODE_ROUTE';
    public const INVALID_NODE_PROGRESSION = 'APPROVAL_FLOW__INVALID_NODE_PROGRESSION';
    public const RESULT_DELIVERY_TRANSACTION_ACTIVE = 'APPROVAL_FLOW__RESULT_DELIVERY_TRANSACTION_ACTIVE';
    public const INVALID_RESULT_DELIVERY = 'APPROVAL_FLOW__INVALID_RESULT_DELIVERY';
    public const RESULT_TRANSACTION_REQUIRED = 'APPROVAL_FLOW__RESULT_TRANSACTION_REQUIRED';
    public const INSTANCE_NOT_FOUND = 'APPROVAL_FLOW__INSTANCE_NOT_FOUND';
    public const INSTANCE_OUTSIDE_SNAPSHOT = 'APPROVAL_FLOW__INSTANCE_OUTSIDE_SNAPSHOT';
    public const INSTANCE_VERSION_CONFLICT = 'APPROVAL_FLOW__INSTANCE_VERSION_CONFLICT';
    public const INSTANCE_COMMAND_CONFLICT = 'APPROVAL_FLOW__INSTANCE_COMMAND_CONFLICT';
    public const INVALID_INSTANCE_TRANSITION = 'APPROVAL_FLOW__INVALID_INSTANCE_TRANSITION';
    public const INVALID_INSTANCE_COMMAND = 'APPROVAL_FLOW__INVALID_INSTANCE_COMMAND';
    public const UNCONTROLLED_INSTANCE_WRITE = 'APPROVAL_FLOW__UNCONTROLLED_INSTANCE_WRITE';
    public const RELEASE_VERIFICATION_FAILED = 'APPROVAL_FLOW__RELEASE_VERIFICATION_FAILED';
    public const RELEASE_NODE_NOT_FOUND = 'APPROVAL_FLOW__RELEASE_NODE_NOT_FOUND';
    public const PUBLICATION_CONTENT_CONFLICT = 'APPROVAL_FLOW__PUBLICATION_CONTENT_CONFLICT';
    public const UNCONTROLLED_RELEASE_WRITE = 'APPROVAL_FLOW__UNCONTROLLED_RELEASE_WRITE';
    public const RELEASE_NOT_FOUND = 'APPROVAL_FLOW__RELEASE_NOT_FOUND';
    public const INVALID_ARTIFACT_MANIFEST = 'APPROVAL_FLOW__INVALID_ARTIFACT_MANIFEST';
    public const ARTIFACT_SERVICE_NOT_FOUND = 'APPROVAL_FLOW__ARTIFACT_SERVICE_NOT_FOUND';
    public const DRAFT_NOT_FOUND = 'APPROVAL_FLOW__DRAFT_NOT_FOUND';
    public const DRAFT_REVISION_CONFLICT = 'APPROVAL_FLOW__DRAFT_REVISION_CONFLICT';
    public const REMOVAL_NOT_CONFIRMED = 'APPROVAL_FLOW__REMOVAL_NOT_CONFIRMED';
    public const UNCONTROLLED_DRAFT_WRITE = 'APPROVAL_FLOW__UNCONTROLLED_DRAFT_WRITE';
    public const INVALID_GRAPH = 'APPROVAL_FLOW__INVALID_GRAPH';
    public const PUBLICATION_GRAPH_INVALID = 'APPROVAL_FLOW__PUBLICATION_GRAPH_INVALID';
    public const INVALID_GRAPH_PLAN = 'APPROVAL_FLOW__INVALID_GRAPH_PLAN';
    public const INVALID_NODE_TYPE = 'APPROVAL_FLOW__INVALID_NODE_TYPE';
    public const DUPLICATE_NODE_TYPE = 'APPROVAL_FLOW__DUPLICATE_NODE_TYPE';
    public const NODE_TYPE_NOT_FOUND = 'APPROVAL_FLOW__NODE_TYPE_NOT_FOUND';
    public const INVALID_BUSINESS_TYPE = 'APPROVAL_FLOW__INVALID_BUSINESS_TYPE';
    public const DUPLICATE_BUSINESS_TYPE = 'APPROVAL_FLOW__DUPLICATE_BUSINESS_TYPE';
    public const BUSINESS_TYPE_NOT_FOUND = 'APPROVAL_FLOW__BUSINESS_TYPE_NOT_FOUND';
    public const NODE_EXECUTOR_NOT_FOUND = 'APPROVAL_FLOW__NODE_EXECUTOR_NOT_FOUND';
    public const DUPLICATE_NODE_EXECUTOR = 'APPROVAL_FLOW__DUPLICATE_NODE_EXECUTOR';
    public const INVALID_NODE_EFFECT = 'APPROVAL_FLOW__INVALID_NODE_EFFECT';
    public const INVALID_NODE_EXECUTOR = 'APPROVAL_FLOW__INVALID_NODE_EXECUTOR';
    public const NODE_COMPILER_NOT_FOUND = 'APPROVAL_FLOW__NODE_COMPILER_NOT_FOUND';
    public const DUPLICATE_NODE_COMPILER = 'APPROVAL_FLOW__DUPLICATE_NODE_COMPILER';
    public const INVALID_NODE_COMPILER = 'APPROVAL_FLOW__INVALID_NODE_COMPILER';
    public const INVALID_NODE_CONFIGURATION = 'APPROVAL_FLOW__INVALID_NODE_CONFIGURATION';

    public static function invalidRequestQuery(string $reason): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::INVALID_REQUEST_QUERY, 'Invalid approval query: {{ reason }}', ['reason' => $reason]);
    }

    public static function resultHandlerNotFound(string $type, string $version): self
    {
        return new self(Response::HTTP_CONFLICT, self::RESULT_HANDLER_NOT_FOUND, 'No result handler is installed for approval business type "{{ type }}@{{ version }}".', ['type' => $type, 'version' => $version]);
    }

    public static function duplicateResultHandler(string $type, string $version, string $first, string $second): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::DUPLICATE_RESULT_HANDLER, 'Result handlers "{{ first }}" and "{{ second }}" both handle "{{ type }}@{{ version }}".', ['type' => $type, 'version' => $version, 'first' => $first, 'second' => $second]);
    }

    public static function resultRequestNotFound(string $instanceId): self
    {
        return new self(Response::HTTP_CONFLICT, self::RESULT_REQUEST_NOT_FOUND, 'The business request or binding for approval instance "{{ instanceId }}" is unavailable.', ['instanceId' => $instanceId]);
    }

    public static function requestNotFound(string $requestId): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::REQUEST_NOT_FOUND, 'Approval request "{{ requestId }}" was not found.', ['requestId' => $requestId]);
    }

    public static function instanceCommandInProgress(string $instanceId): self
    {
        return new self(Response::HTTP_CONFLICT, self::INSTANCE_COMMAND_IN_PROGRESS, 'Approval instance "{{ instanceId }}" is already being changed by this operation.', ['instanceId' => $instanceId]);
    }

    public static function requestActorRequired(): self
    {
        return new self(Response::HTTP_FORBIDDEN, self::REQUEST_ACTOR_REQUIRED, 'An administration user is required to access approval requests.');
    }

    public static function requestInitiatorRequired(string $requestId): self
    {
        return new self(Response::HTTP_FORBIDDEN, self::REQUEST_INITIATOR_REQUIRED, 'Only the initiator may retry approval request "{{ requestId }}".', ['requestId' => $requestId]);
    }

    public static function invalidRequestForm(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::INVALID_REQUEST_FORM, 'Invalid approval form at "{{ path }}": {{ reason }}', ['path' => $path, 'reason' => $reason], $previous);
    }

    public static function requestTransactionRequired(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::REQUEST_TRANSACTION_REQUIRED, 'Selecting a submission binding requires an active transaction.');
    }

    /**
     * @param list<string> $privileges
     */
    public static function missingPrivileges(array $privileges): MissingPrivilegeException
    {
        return new MissingPrivilegeException($privileges);
    }

    public static function bindingWriteInProgress(string $type): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_WRITE_IN_PROGRESS,
            'Approval binding "{{ type }}" is already being changed by this operation.',
            ['type' => $type]
        );
    }

    public static function bindingNotFound(string $type, int $revision): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::BINDING_NOT_FOUND,
            'Approval binding "{{ type }}" revision {{ revision }} was not found.',
            ['type' => $type, 'revision' => $revision]
        );
    }

    public static function activeBindingNotFound(string $type): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::ACTIVE_BINDING_NOT_FOUND,
            'No active approval binding is available for "{{ type }}".',
            ['type' => $type]
        );
    }

    public static function bindingRevisionConflict(string $type, int $expectedRevision, int $actualRevision): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_REVISION_CONFLICT,
            'Approval binding "{{ type }}" changed from expected revision {{ expectedRevision }} to {{ actualRevision }}.',
            ['type' => $type, 'expectedRevision' => $expectedRevision, 'actualRevision' => $actualRevision]
        );
    }

    public static function bindingVersionConflict(string $type, int $expectedVersion, int $actualVersion): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_VERSION_CONFLICT,
            'Approval binding "{{ type }}" activation changed from expected version {{ expectedVersion }} to {{ actualVersion }}.',
            ['type' => $type, 'expectedVersion' => $expectedVersion, 'actualVersion' => $actualVersion]
        );
    }

    public static function bindingReleaseMismatch(string $type, string $field): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::BINDING_RELEASE_MISMATCH,
            'Approval release does not match business type "{{ type }}" at "{{ field }}".',
            ['type' => $type, 'field' => $field]
        );
    }

    public static function bindingFormChanged(string $type, string $version): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::BINDING_FORM_CHANGED,
            'Approval business form "{{ type }}@{{ version }}" differs from the saved binding.',
            ['type' => $type, 'version' => $version]
        );
    }

    public static function uncontrolledBindingWrite(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::UNCONTROLLED_BINDING_WRITE,
            'Approval bindings must be changed through the controlled binding service.',
            []
        );
    }

    public static function instanceNotFound(string $instanceId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::INSTANCE_NOT_FOUND,
            'Approval instance "{{ instanceId }}" was not found.',
            ['instanceId' => $instanceId]
        );
    }

    public static function instanceVersionConflict(string $instanceId, int $expectedVersion, int $actualVersion): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INSTANCE_VERSION_CONFLICT,
            'Approval instance "{{ instanceId }}" changed from expected version {{ expectedVersion }} to {{ actualVersion }}.',
            ['instanceId' => $instanceId, 'expectedVersion' => $expectedVersion, 'actualVersion' => $actualVersion]
        );
    }

    public static function instanceCommandConflict(string $instanceId, string $commandId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INSTANCE_COMMAND_CONFLICT,
            'Command "{{ commandId }}" was already used for another request on approval instance "{{ instanceId }}".',
            ['instanceId' => $instanceId, 'commandId' => $commandId]
        );
    }

    public static function invalidInstanceTransition(string $instanceId, string $status, string $operation): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INVALID_INSTANCE_TRANSITION,
            'Cannot {{ operation }} approval instance "{{ instanceId }}" in status "{{ status }}".',
            ['instanceId' => $instanceId, 'status' => $status, 'operation' => $operation]
        );
    }

    public static function invalidInstanceCommand(string $reason): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_INSTANCE_COMMAND,
            'Invalid approval instance command: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function instanceOutsideSnapshot(string $instanceId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INSTANCE_OUTSIDE_SNAPSHOT,
            'Approval instance "{{ instanceId }}" is outside the current transaction snapshot. Retry the command in a new transaction.',
            ['instanceId' => $instanceId]
        );
    }

    public static function startNodeAlreadyEntered(string $instanceId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::START_NODE_ALREADY_ENTERED,
            'Approval instance "{{ instanceId }}" has already entered its start node.',
            ['instanceId' => $instanceId]
        );
    }

    public static function unsupportedStartRoute(string $releaseId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::UNSUPPORTED_START_ROUTE,
            'Approval release "{{ releaseId }}" requires a single start edge into a node with no other incoming edges.',
            ['releaseId' => $releaseId]
        );
    }

    public static function uncontrolledInstanceWrite(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::UNCONTROLLED_INSTANCE_WRITE,
            'Approval instance state, node executions, tasks, results and immutable command receipts require a controlled command transaction.'
        );
    }

    public static function resultDeliveryTransactionActive(): self
    {
        return new self(Response::HTTP_CONFLICT, self::RESULT_DELIVERY_TRANSACTION_ACTIVE, 'Approval result delivery requires its own committed transaction.');
    }

    public static function invalidResultDelivery(): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::INVALID_RESULT_DELIVERY, 'A valid result ID and an attempt within the delivery limit are required.');
    }

    public static function resultTransactionRequired(): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, self::RESULT_TRANSACTION_REQUIRED, 'Approval result recording requires the instance command transaction.');
    }

    public static function unsupportedNodeRoute(string $releaseId, string $nodeId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::UNSUPPORTED_NODE_ROUTE,
            'Node "{{ nodeId }}" in approval release "{{ releaseId }}" requires a single unconditional edge into a node with no other incoming edges.',
            ['releaseId' => $releaseId, 'nodeId' => $nodeId]
        );
    }

    public static function invalidNodeProgression(string $executionId, string $reason): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INVALID_NODE_PROGRESSION,
            'Node execution "{{ executionId }}" cannot progress: {{ reason }}',
            ['executionId' => $executionId, 'reason' => $reason]
        );
    }

    public static function executionNotFound(string $executionId, string $instanceId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::EXECUTION_NOT_FOUND,
            'Node execution "{{ executionId }}" was not found on approval instance "{{ instanceId }}".',
            ['executionId' => $executionId, 'instanceId' => $instanceId]
        );
    }

    public static function taskNotFound(string $taskId): self
    {
        return new self(Response::HTTP_NOT_FOUND, self::TASK_NOT_FOUND, 'Approval task "{{ taskId }}" was not found on this instance.', ['taskId' => $taskId]);
    }

    public static function singleApprovalAssigneeRequired(): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::SINGLE_APPROVAL_ASSIGNEE_REQUIRED,
            'Single approval configuration requires exactly one candidate.'
        );
    }

    public static function taskNotPending(string $taskId): self
    {
        return new self(Response::HTTP_CONFLICT, self::TASK_NOT_PENDING, 'Approval task "{{ taskId }}" is no longer pending.', ['taskId' => $taskId]);
    }

    public static function taskAssigneeRequired(): self
    {
        return new self(Response::HTTP_FORBIDDEN, self::TASK_ASSIGNEE_REQUIRED, 'The active assigned Administration user must make this decision.');
    }

    public static function taskOutsideSnapshot(string $taskId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::TASK_OUTSIDE_SNAPSHOT,
            'Approval task "{{ taskId }}" is outside the current transaction snapshot. Retry the command in a new transaction.',
            ['taskId' => $taskId]
        );
    }

    public static function executionOutsideSnapshot(string $executionId): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::EXECUTION_OUTSIDE_SNAPSHOT,
            'Node execution "{{ executionId }}" is outside the current transaction snapshot. Retry the command in a new transaction.',
            ['executionId' => $executionId]
        );
    }

    public static function invalidApprovalEntry(string $executionId, string $reason): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::INVALID_APPROVAL_ENTRY,
            'Cannot enter approval execution "{{ executionId }}": {{ reason }}',
            ['executionId' => $executionId, 'reason' => $reason]
        );
    }

    public static function assigneeUnavailable(string $userId): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::ASSIGNEE_UNAVAILABLE,
            'Approval assignee "{{ userId }}" does not identify an active Administration account.',
            ['userId' => $userId]
        );
    }

    public static function emptyApprovalAssignees(): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::EMPTY_APPROVAL_ASSIGNEES,
            'Approval configuration requires at least one candidate.'
        );
    }

    public static function publicationContentConflict(string $definitionId, string $expectedHash, string $actualHash): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::PUBLICATION_CONTENT_CONFLICT,
            'Approval publication content changed for "{{ definitionId }}"; prepare a new preview.',
            ['definitionId' => $definitionId, 'expectedHash' => $expectedHash, 'actualHash' => $actualHash]
        );
    }

    public static function releaseVerificationFailed(string $releaseId, string $part): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::RELEASE_VERIFICATION_FAILED,
            'Approval release "{{ releaseId }}" does not match this deployment at "{{ part }}".',
            ['releaseId' => $releaseId, 'part' => $part]
        );
    }

    public static function releaseNodeNotFound(string $releaseId, string $nodeId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::RELEASE_NODE_NOT_FOUND,
            'Approval release "{{ releaseId }}" has no node "{{ nodeId }}".',
            ['releaseId' => $releaseId, 'nodeId' => $nodeId]
        );
    }

    public static function uncontrolledReleaseWrite(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::UNCONTROLLED_RELEASE_WRITE,
            'Approval releases allow controlled insertion only; their content cannot be updated or deleted.'
        );
    }

    public static function releaseNotFound(string $releaseId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::RELEASE_NOT_FOUND,
            'Approval release "{{ releaseId }}" was not found.',
            ['releaseId' => $releaseId]
        );
    }

    public static function invalidArtifactManifest(string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ARTIFACT_MANIFEST,
            'Invalid approval artifact manifest: {{ reason }}',
            ['reason' => $reason],
            $previous
        );
    }

    public static function artifactServiceNotFound(string $service): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::ARTIFACT_SERVICE_NOT_FOUND,
            'No retained artifact is bound to approval service "{{ service }}".',
            ['service' => $service]
        );
    }

    public static function draftNotFound(string $definitionId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::DRAFT_NOT_FOUND,
            'No approval draft exists for definition "{{ definitionId }}".',
            ['definitionId' => $definitionId]
        );
    }

    public static function draftRevisionConflict(string $definitionId, int $expectedRevision, int $actualRevision): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::DRAFT_REVISION_CONFLICT,
            'Approval draft "{{ definitionId }}" is at revision {{ actualRevision }}, expected {{ expectedRevision }}.',
            ['definitionId' => $definitionId, 'expectedRevision' => $expectedRevision, 'actualRevision' => $actualRevision]
        );
    }

    public static function removalNotConfirmed(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::REMOVAL_NOT_CONFIRMED,
            'Confirm the removed graph fragments before saving the draft.'
        );
    }

    public static function uncontrolledDraftWrite(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::UNCONTROLLED_DRAFT_WRITE,
            'Approval definitions and drafts must be written through the approval draft service.'
        );
    }

    public static function invalidGraph(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_GRAPH,
            'Invalid approval graph at "{{ path }}": {{ reason }}',
            ['path' => $path, 'reason' => $reason],
            $previous
        );
    }

    public static function publicationGraphInvalid(string $code, string $path, string $reason): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::PUBLICATION_GRAPH_INVALID,
            'Approval graph cannot be prepared for publication at "{{ path }}" ({{ code }}): {{ reason }}',
            ['code' => $code, 'path' => $path, 'reason' => $reason]
        );
    }

    public static function invalidGraphPlan(string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_GRAPH_PLAN,
            'Invalid compiled approval graph plan: {{ reason }}',
            ['reason' => $reason]
        );
    }

    public static function invalidBusinessType(string $source, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_BUSINESS_TYPE,
            'Invalid approval business type declaration "{{ source }}": {{ reason }}',
            ['source' => $source, 'reason' => $reason],
            $previous
        );
    }

    public static function duplicateBusinessType(string $type, string $version, string $firstSource, string $secondSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_BUSINESS_TYPE,
            'Approval business type "{{ type }}@{{ version }}" is declared by both "{{ first }}" and "{{ second }}".',
            ['type' => $type, 'version' => $version, 'first' => $firstSource, 'second' => $secondSource]
        );
    }

    public static function businessTypeNotFound(string $type, string $version): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::BUSINESS_TYPE_NOT_FOUND,
            'Approval business type "{{ type }}@{{ version }}" is not available.',
            ['type' => $type, 'version' => $version]
        );
    }

    public static function invalidNodeType(string $source, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_NODE_TYPE,
            'Invalid approval node declaration "{{ source }}": {{ reason }}',
            ['source' => $source, 'reason' => $reason],
            $previous
        );
    }

    public static function duplicateNodeType(string $type, string $version, string $firstSource, string $secondSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_NODE_TYPE,
            'Approval node "{{ type }}@{{ version }}" is declared by both "{{ first }}" and "{{ second }}".',
            ['type' => $type, 'version' => $version, 'first' => $firstSource, 'second' => $secondSource]
        );
    }

    public static function nodeTypeNotFound(string $type, string $version): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::NODE_TYPE_NOT_FOUND,
            'Approval node "{{ type }}@{{ version }}" is not available.',
            ['type' => $type, 'version' => $version]
        );
    }

    public static function nodeExecutorNotFound(string $type, string $version): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::NODE_EXECUTOR_NOT_FOUND,
            'No executor is available for approval node "{{ type }}@{{ version }}".',
            ['type' => $type, 'version' => $version]
        );
    }

    public static function duplicateNodeExecutor(string $type, string $version, string $first, string $second): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_NODE_EXECUTOR,
            'Approval node "{{ type }}@{{ version }}" has two executors: "{{ first }}" and "{{ second }}".',
            ['type' => $type, 'version' => $version, 'first' => $first, 'second' => $second]
        );
    }

    public static function invalidNodeEffect(string $type, string $version, string $effect, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_NODE_EFFECT,
            'Invalid approval node effect "{{ effect }}" from "{{ type }}@{{ version }}": {{ reason }}',
            ['type' => $type, 'version' => $version, 'effect' => $effect, 'reason' => $reason]
        );
    }

    public static function invalidNodeExecutor(string $executor, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_NODE_EXECUTOR,
            'Invalid approval node executor "{{ executor }}": {{ reason }}',
            ['executor' => $executor, 'reason' => $reason]
        );
    }

    public static function nodeCompilerNotFound(string $type, string $version): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::NODE_COMPILER_NOT_FOUND,
            'No compiler is available for approval node "{{ type }}@{{ version }}".',
            ['type' => $type, 'version' => $version]
        );
    }

    public static function duplicateNodeCompiler(string $type, string $version, string $first, string $second): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_NODE_COMPILER,
            'Approval node "{{ type }}@{{ version }}" has two compilers: "{{ first }}" and "{{ second }}".',
            ['type' => $type, 'version' => $version, 'first' => $first, 'second' => $second]
        );
    }

    public static function invalidNodeCompiler(string $compiler, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_NODE_COMPILER,
            'Invalid approval node compiler "{{ compiler }}": {{ reason }}',
            ['compiler' => $compiler, 'reason' => $reason],
            $previous
        );
    }

    public static function invalidNodeConfiguration(string $type, string $version, string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            self::INVALID_NODE_CONFIGURATION,
            'Invalid approval node configuration "{{ type }}@{{ version }}" at "{{ path }}": {{ reason }}',
            ['type' => $type, 'version' => $version, 'path' => $path, 'reason' => $reason],
            $previous
        );
    }
}
