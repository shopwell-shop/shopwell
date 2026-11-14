<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Graph;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 契约里的一个节点，**只保留契约字段**。
 *
 * 前端（Vue Flow）的节点对象比这里多得多：`data`、`position`、`selected`、
 * `dragging`、`computedPosition`…… 那些是渲染状态，不是流程定义。
 * 解析时按白名单取字段，落库的 `graph_snapshot` 里就不会混进 UI 噪声，
 * 也保证同一份图在不同版本的设计器里解析结果一致。
 */
#[Package('framework')]
final class GraphNode
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly float $x,
        public readonly float $y,
        public readonly array $properties,
    ) {
    }

    /**
     * 读一个节点属性，不存在时给默认值。
     *
     * 刻意不提供 `setProperty()`：节点是不可变的。运行期要改属性请改实例的
     * `ext`（或退回上下文），不要改图——图是模板的快照，改它等于篡改历史。
     */
    public function property(string $key, mixed $default = null): mixed
    {
        return $this->properties[$key] ?? $default;
    }

    /**
     * 展示名。
     *
     * 名字只用于界面和报错文案，缺了不能让流程跑不动：退回节点 id，
     * 至少能在异常信息里认出是哪个节点。名字为空串也当缺失——
     * 设计器允许清空输入框，空串会让日志里出现"由  审批"这种句子。
     */
    public function name(): string
    {
        $name = $this->property('name');

        return \is_string($name) && trim($name) !== '' ? $name : $this->id;
    }

    /**
     * @return array{id: string, type: string, x: float, y: float, properties: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'x' => $this->x,
            'y' => $this->y,
            'properties' => $this->properties,
        ];
    }
}
