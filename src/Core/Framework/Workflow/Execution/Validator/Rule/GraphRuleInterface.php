<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphData;
use Shopwell\Core\Framework\Workflow\Execution\Validator\ValidationContext;
use Shopwell\Core\Framework\Workflow\Execution\Validator\Violation;

/**
 * @internal Experimental execution extension point; not covered by the backward compatibility promise.
 *
 * 一条图校验规则。
 *
 * 用规则链而不是一个大 `validate()` 方法，是为了：
 * 1. 新增节点类型时只加一条规则，不改主循环；
 * 2. 单测能一条一条测，失败信息直接指向规则；
 * 3. 规则可以被"软禁用"（比如某些租户允许不汇聚）——换注册表内容即可。
 */
#[Package('framework')]
interface GraphRuleInterface
{
    /**
     * @return list<Violation>
     */
    public function validate(GraphData $graph, ValidationContext $context): array;
}
