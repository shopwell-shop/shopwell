<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class WorkflowException extends HttpException
{
    public static function missingPublicationPrivilege(string $privilege): self
    {
        return new self(Response::HTTP_FORBIDDEN, 'FRAMEWORK__WORKFLOW_MISSING_PUBLICATION_PRIVILEGE', 'Missing privilege "{{ privilege }}" for workflow publication.', ['privilege' => $privilege]);
    }

    public static function unknownDefaultRole(string $role): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__WORKFLOW_UNKNOWN_DEFAULT_ROLE', 'Unknown default workflow role "{{ role }}".', ['role' => $role]);
    }

    /**
     * @param list<WorkflowViolation> $violations
     */
    public static function publicationRejected(string $reason, array $violations = []): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'FRAMEWORK__WORKFLOW_PUBLICATION_REJECTED',
            'Workflow cannot be published: {{ reason }}.',
            ['reason' => $reason, 'violations' => $violations]
        );
    }

    public static function publisherUnavailable(string $name): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_PUBLISHER_UNAVAILABLE', 'Workflow publisher "{{ name }}" is unavailable.', ['name' => $name]);
    }

    public static function duplicatePublisher(string $name): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_DUPLICATE_PUBLISHER', 'Workflow publisher "{{ name }}" is registered more than once.', ['name' => $name]);
    }

    public static function releaseNotFound(string $id): self
    {
        return new self(Response::HTTP_NOT_FOUND, 'FRAMEWORK__WORKFLOW_RELEASE_NOT_FOUND', 'Workflow release "{{ id }}" was not found.', ['id' => $id]);
    }

    public static function draftNotFound(string $id): self
    {
        return new self(Response::HTTP_NOT_FOUND, 'FRAMEWORK__WORKFLOW_DRAFT_NOT_FOUND', 'Workflow draft "{{ id }}" was not found.', ['id' => $id]);
    }

    public static function draftConflict(string $id): self
    {
        return new self(Response::HTTP_CONFLICT, 'FRAMEWORK__WORKFLOW_DRAFT_CONFLICT', 'Workflow draft "{{ id }}" changed. Reload before saving.', ['id' => $id]);
    }

    public static function duplicateGraphPolicy(string $name): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_DUPLICATE_GRAPH_POLICY', 'Workflow graph policy "{{ name }}" is registered more than once.', ['name' => $name]);
    }

    public static function invalidDocument(string $path, string $reason): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__WORKFLOW_INVALID_DOCUMENT', 'Invalid workflow document at {{ path }}: {{ reason }}', ['path' => $path, 'reason' => $reason]);
    }

    public static function invalidNodeType(string $name): self
    {
        return new self(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__WORKFLOW_INVALID_NODE_TYPE', 'Invalid workflow node type "{{ name }}".', ['name' => $name]);
    }

    public static function duplicateNodeType(string $name, int $version): self
    {
        return new self(Response::HTTP_INTERNAL_SERVER_ERROR, 'FRAMEWORK__WORKFLOW_DUPLICATE_NODE_TYPE', 'Workflow node type "{{ name }}" version {{ version }} is registered more than once.', ['name' => $name, 'version' => $version]);
    }
}
