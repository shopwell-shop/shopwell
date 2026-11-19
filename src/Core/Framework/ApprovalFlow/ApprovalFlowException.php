<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ApprovalFlowException extends HttpException
{
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
}
