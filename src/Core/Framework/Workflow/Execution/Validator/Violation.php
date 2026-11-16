<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;

/**
 * A validation result with a shared frontend/backend code and optional node or edge location.
 *
 * @internal
 */
#[Package('framework')]
final class Violation
{
    public const LEVEL_ERROR = 'error';
    public const LEVEL_WARNING = 'warning';

    private function __construct(
        public readonly string $code,
        public readonly string $level,
        public readonly string $message,
        public readonly ?string $nodeId = null,
        public readonly ?string $edgeId = null,
    ) {
    }

    public static function error(string $code, string $message, ?string $nodeId = null, ?string $edgeId = null): self
    {
        return new self($code, self::LEVEL_ERROR, $message, $nodeId, $edgeId);
    }

    /**
     * Warnings do not prevent saving.
     */
    public static function warning(string $code, string $message, ?string $nodeId = null, ?string $edgeId = null): self
    {
        return new self($code, self::LEVEL_WARNING, $message, $nodeId, $edgeId);
    }

    public function isError(): bool
    {
        return $this->level === self::LEVEL_ERROR;
    }

    /**
     * @return array{code: string, level: string, message: string, nodeId: string|null, edgeId: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'level' => $this->level,
            'message' => $this->message,
            'nodeId' => $this->nodeId,
            'edgeId' => $this->edgeId,
        ];
    }
}
