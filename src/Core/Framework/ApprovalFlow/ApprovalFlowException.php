<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow;

use Shopwell\Core\Framework\HttpException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ApprovalFlowException extends HttpException
{
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
