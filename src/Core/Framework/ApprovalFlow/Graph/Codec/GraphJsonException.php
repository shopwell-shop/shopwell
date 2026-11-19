<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Codec;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphViolation;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
final class GraphJsonException extends ApprovalFlowException
{
    public function __construct(public readonly GraphViolation $violation, ?\Throwable $previous = null)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_GRAPH,
            'Invalid approval graph at "{{ path }}": {{ reason }}',
            ['path' => $violation->path, 'reason' => $violation->message],
            $previous
        );
    }

    public static function forViolation(GraphViolation $violation, ?\Throwable $previous = null): self
    {
        return new self($violation, $previous);
    }
}
