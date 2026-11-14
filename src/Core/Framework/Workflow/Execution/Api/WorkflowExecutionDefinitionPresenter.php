<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Definition\WorkflowExecutionDefinitionEntity;

/**
 * @internal
 *
 * 模板输出。
 *
 * 控制器不拼数组：输出结构只在这里定义一次，字段改名时不会漏掉某个接口。
 * 列表与详情的差别用参数控制，不写两个方法——两份实现迟早会不一致。
 */
#[Package('framework')]
final class WorkflowExecutionDefinitionPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(WorkflowExecutionDefinitionEntity $definition, Context $context, bool $withGraph = true): array
    {
        $payload = [
            'id' => $definition->id,
            'code' => $definition->code,
            'name' => $definition->name,
            'description' => $definition->description,
            'status' => $definition->status,
            'version' => $definition->version,
            'isLatest' => $definition->isLatest,
            'formSchema' => $definition->formFields(),
            'updatedAt' => $definition->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];

        if ($withGraph) {
            // 列表接口不带图：一张图几十 KB，20 条模板就是 1MB，
            // 而列表页只需要名称和状态
            $payload['graphData'] = $definition->graph();
        }

        return $payload;
    }
}
