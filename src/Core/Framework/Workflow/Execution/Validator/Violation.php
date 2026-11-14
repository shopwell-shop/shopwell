<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 一条校验结果。
 *
 * `code` 是**前后端共用**的错误码（`WORKFLOW_EXECUTION_001`…），前端 `graph-validator.ts`
 * 必须用同一套码。原因：设计器把错误定位到节点靠 code 分组，
 * 两端口径不一致时会出现"后端说有错、前端点不到哪个节点"。
 *
 * 一条违规尽量带 `nodeId` / `edgeId`：只给文案的校验结果，在 20 个节点的图上
 * 等于没给——用户得自己一个个点开找。
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
     * 警告不拦截保存：钉钉允许"分支各自到结束"，我们只提示不强制汇聚。
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
