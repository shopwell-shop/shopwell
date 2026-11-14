<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 *
 * 契约表达式与求值器表达式之间的翻译，外加保存期的语法/变量校验。
 *
 * 契约写法（设计器输出、后端原样存库）：
 *   `${form.amount} > 5000`
 *   `${form.type} == '采购'`
 *
 * 求值器写法（qlexpress / aviator 都吃这个）：
 *   `amount > 5000`
 *
 * 单独一个类而不是塞进求值器，是因为**保存模板**时要用它做校验（那时还没有求值器），
 * 而**运行期**求值也要用它做归一化。两边共用一份正则，才不会出现
 * "保存时认得、运行时认不得"。
 */
#[Package('framework')]
final class ExprNormalizer
{
    /**
     * 只匹配 `${form.key}` 这一种引用。
     *
     * 刻意不支持 `${user.id}`、`${instance.xxx}`：条件只能看表单变量。
     * 一旦允许引用审批人、部门这类运行期数据，同一个表达式在不同时间会算出不同结果，
     * 流程路径就无法复现，出问题也没法解释。
     */
    private const VARIABLE_PATTERN = '/\$\{\s*form\.([A-Za-z_][A-Za-z0-9_]*)\s*\}/';

    /**
     * 归一化后允许出现的字符：标识符、数字、字符串、比较运算符、括号、空白。
     * 任何别的字符（分号、反引号、`$`、花括号）一律拒绝——这是防注入的第一层。
     */
    private const ALLOWED_PATTERN = '/^[A-Za-z0-9_.\s\'"()<>=!+\-*\/%,]*$/D';

    /**
     * 把 `${form.amount}` 换成 `amount`。
     *
     * 用 `preg_replace_callback` 而不是直接替换：变量名要过一遍白名单校验，
     * 非法名字（`${form.2x}`）在替换阶段就报错，而不是留给求值器抛语法异常。
     */
    public function normalize(string $expr): string
    {
        $normalized = preg_replace_callback(
            self::VARIABLE_PATTERN,
            static function (array $matches): string {
                $key = $matches[1];

                if (str_starts_with($key, '_')) {
                    // 下划线开头留给内部变量，业务表单不许用
                    throw WorkflowExecutionException::conditionInvalid($matches[0], '变量名不能以下划线开头');
                }

                return $key;
            },
            $expr
        );

        if ($normalized === null) {
            throw WorkflowExecutionException::conditionInvalid($expr, '无法解析变量引用');
        }

        return trim($normalized);
    }

    /**
     * 表达式里引用到的表单变量名（去重、保持出现顺序）。
     *
     * @return list<string>
     */
    public function variables(string $expr): array
    {
        $matched = preg_match_all(self::VARIABLE_PATTERN, $expr, $matches);

        if ($matched === false || $matched === 0) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * 保存模板时校验：语法可解析 + 变量都在表单里。
     *
     * `$allowedKeys` 来自模板的 `formSchema`。**空表单也要传空数组**，
     * 此时任何变量引用都会报错——这是对的：没有表单字段的流程不可能有条件分支。
     *
     * @param list<string> $allowedKeys
     *
     * @throws WorkflowExecutionException
     */
    public function assertValid(string $expr, array $allowedKeys): void
    {
        if (trim($expr) === '') {
            throw WorkflowExecutionException::conditionInvalid($expr, '表达式为空');
        }

        foreach ($this->variables($expr) as $variable) {
            if (!\in_array($variable, $allowedKeys, true)) {
                throw WorkflowExecutionException::conditionInvalid($expr, \sprintf('表单里没有字段 "%s"', $variable));
            }
        }

        // 每个变量引用都必须写成 `${form.x}`：`amount > 5` 这种裸写能跑通求值，
        // 但设计器渲染不出条件标签（面板只认 ${form.x}），保存后用户看不到自己配了什么。
        //
        // 做法：把合法引用替换成数字（数字不匹配标识符正则），再找剩下的标识符。
        // 只剩 true/false/null 才放行——其它任何标识符都是"裸写的变量名"。
        $withoutRefs = preg_replace(self::VARIABLE_PATTERN, '1', $expr) ?? '';
        $stripped = preg_replace('/\'[^\']*\'|"[^"]*"/', '', $withoutRefs) ?? $withoutRefs;

        if (preg_match('/\b[A-Za-z_][A-Za-z0-9_]*\b/', $stripped, $matches) === 1
            && !\in_array(strtolower($matches[0]), ['true', 'false', 'null'], true)
        ) {
            throw WorkflowExecutionException::conditionInvalid($expr, '变量必须写成 ${form.字段名}');
        }

        $normalized = $this->normalize($expr);

        if (preg_match(self::ALLOWED_PATTERN, $normalized) !== 1) {
            throw WorkflowExecutionException::conditionInvalid($expr, '包含不允许的字符');
        }

        // 归一化后不该再有 `$` 或花括号；有的话说明用了别的引用形式
        if (str_contains($normalized, '$') || str_contains($normalized, '{')) {
            throw WorkflowExecutionException::conditionInvalid($expr, '只允许引用 ${form.字段名}');
        }
    }
}
