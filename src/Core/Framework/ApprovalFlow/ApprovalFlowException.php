<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ApprovalFlowException extends HttpException
{
    /**
     * @param list<string> $privileges
     */
    public static function missingPrivileges(array $privileges): MissingPrivilegeException
    {
        return new MissingPrivilegeException($privileges);
    }

    public static function draftNotFound(string $definitionId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            'APPROVAL_FLOW__DRAFT_NOT_FOUND',
            'No approval draft exists for definition "{{ definitionId }}".',
            ['definitionId' => $definitionId]
        );
    }

    public static function draftRevisionConflict(string $definitionId, int $expectedRevision, int $actualRevision): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            'APPROVAL_FLOW__DRAFT_REVISION_CONFLICT',
            'Approval draft "{{ definitionId }}" is at revision {{ actualRevision }}, expected {{ expectedRevision }}.',
            ['definitionId' => $definitionId, 'expectedRevision' => $expectedRevision, 'actualRevision' => $actualRevision]
        );
    }

    public static function removalNotConfirmed(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            'APPROVAL_FLOW__REMOVAL_NOT_CONFIRMED',
            'Confirm the removed graph fragments before saving the draft.'
        );
    }

    public static function uncontrolledDraftWrite(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            'APPROVAL_FLOW__UNCONTROLLED_DRAFT_WRITE',
            'Approval definitions and drafts must be written through the approval draft service.'
        );
    }

    public static function invalidGraph(string $path, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            'APPROVAL_FLOW__INVALID_GRAPH',
            'Invalid approval graph at "{{ path }}": {{ reason }}',
            ['path' => $path, 'reason' => $reason],
            $previous
        );
    }

    public static function invalidNodeType(string $source, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'APPROVAL_FLOW__INVALID_NODE_TYPE',
            'Invalid approval node declaration "{{ source }}": {{ reason }}',
            ['source' => $source, 'reason' => $reason],
            $previous
        );
    }

    public static function duplicateNodeType(string $type, string $version, string $firstSource, string $secondSource): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'APPROVAL_FLOW__DUPLICATE_NODE_TYPE',
            'Approval node "{{ type }}@{{ version }}" is declared by both "{{ first }}" and "{{ second }}".',
            ['type' => $type, 'version' => $version, 'first' => $firstSource, 'second' => $secondSource]
        );
    }

    public static function nodeTypeNotFound(string $type, string $version): self
    {
        return new self(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'APPROVAL_FLOW__NODE_TYPE_NOT_FOUND',
            'Approval node "{{ type }}@{{ version }}" is not available.',
            ['type' => $type, 'version' => $version]
        );
    }
}
