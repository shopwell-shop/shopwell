<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Exception;

use Shopwell\Core\Content\Flow\FlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class ExecuteSequenceException extends FlowException
{
    final public const SEQUENCE_EXECUTION_FAILED = 'SEQUENCE_EXECUTION_FAILED';

    public function __construct(
        private readonly string $flowId,
        private readonly string $sequenceId,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            statusCode: $code,
            errorCode: self::SEQUENCE_EXECUTION_FAILED,
            message: $message,
            previous: $previous
        );
    }

    public static function sequenceExecutionFailed(
        string $flowId,
        string $sequenceId,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ): self {
        return new self(
            flowId: $flowId,
            sequenceId: $sequenceId,
            message: $message,
            code: $code,
            previous: $previous
        );
    }

    public function getFlowId(): string
    {
        return $this->flowId;
    }

    public function getSequenceId(): string
    {
        return $this->sequenceId;
    }
}
