<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ApprovalFlowException extends HttpException
{
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
    public const NODE_EXECUTOR_NOT_FOUND = 'APPROVAL_FLOW__NODE_EXECUTOR_NOT_FOUND';
    public const DUPLICATE_NODE_EXECUTOR = 'APPROVAL_FLOW__DUPLICATE_NODE_EXECUTOR';
    public const INVALID_NODE_EXECUTOR = 'APPROVAL_FLOW__INVALID_NODE_EXECUTOR';
    public const NODE_COMPILER_NOT_FOUND = 'APPROVAL_FLOW__NODE_COMPILER_NOT_FOUND';
    public const DUPLICATE_NODE_COMPILER = 'APPROVAL_FLOW__DUPLICATE_NODE_COMPILER';
    public const INVALID_NODE_COMPILER = 'APPROVAL_FLOW__INVALID_NODE_COMPILER';
    public const INVALID_NODE_CONFIGURATION = 'APPROVAL_FLOW__INVALID_NODE_CONFIGURATION';

    /**
     * @param list<string> $privileges
     */
    public static function missingPrivileges(array $privileges): MissingPrivilegeException
    {
        return new MissingPrivilegeException($privileges);
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
