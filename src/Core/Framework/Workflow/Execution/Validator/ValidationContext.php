<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Validator;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 校验时能用到的外部事实。
 *
 * 刻意只放"模板保存那一刻能确定的东西"：表单字段、字段类型。
 * 用户/角色是否存在**不放进校验器**——那需要查库，而校验器要保持纯函数
 * （单测里不用起数据库）。审批人是否存在由 {@see \Shopwell\Core\Framework\Workflow\Execution\Assignee\AssigneeResolverRegistry}
 * 在**发起时**解析并报错，那时才知道真实的人员数据。
 */
#[Package('framework')]
final class ValidationContext
{
    /**
     * @param list<string> $formKeys 表单字段名，条件表达式只能引用它们
     * @param array<string, string> $formTypes 字段名 → 类型（number/string/...），目前只用于提示
     */
    public function __construct(
        public readonly array $formKeys = [],
        public readonly array $formTypes = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * 从模板的 `formSchema` 构造：`[{key,label,type}]`。
     *
     * @param list<array<string, mixed>>|null $formSchema
     */
    public static function fromFormSchema(?array $formSchema): self
    {
        $keys = [];
        $types = [];

        foreach ($formSchema ?? [] as $field) {
            if (!\is_array($field) || !isset($field['key']) || !\is_string($field['key']) || $field['key'] === '') {
                continue;
            }

            $keys[] = $field['key'];
            $types[$field['key']] = \is_string($field['type'] ?? null) ? $field['type'] : 'string';
        }

        return new self($keys, $types);
    }

    public function hasFormKey(string $key): bool
    {
        return \in_array($key, $this->formKeys, true);
    }
}
