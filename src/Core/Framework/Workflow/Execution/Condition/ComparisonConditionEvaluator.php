<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Evaluates explicit comparisons joined by && or || over form values.
 * Function calls, arithmetic and nested parentheses are unsupported and rejected.
 * Published approval workflows use typed ApprovalCondition expressions instead.
 *
 * @internal
 */
#[Package('framework')]
final class ComparisonConditionEvaluator implements ConditionEvaluatorInterface
{
    /**
     * 运算符按长度降序，避免 `>=` 被 `>` 先匹配掉
     */
    private const OPERATORS = ['>=', '<=', '==', '!=', '>', '<'];

    public function __construct(private readonly ExprNormalizer $normalizer)
    {
    }

    public function evaluate(string $expr, array $formData): bool
    {
        $normalized = $this->normalizer->normalize($expr);

        return $this->evaluateNormalized($normalized, $formData);
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function evaluateNormalized(string $expr, array $formData): bool
    {
        $expr = trim($expr);

        if ($expr === '') {
            throw WorkflowExecutionException::conditionInvalid($expr, '表达式为空');
        }

        // 或：任意一侧为真即可。先切 `||` 再切 `&&`，与常规优先级一致
        $orParts = $this->splitTopLevel($expr, '||');

        if (\count($orParts) > 1) {
            foreach ($orParts as $part) {
                if ($this->evaluateNormalized($part, $formData)) {
                    return true;
                }
            }

            return false;
        }

        $andParts = $this->splitTopLevel($expr, '&&');

        if (\count($andParts) > 1) {
            foreach ($andParts as $part) {
                if (!$this->evaluateNormalized($part, $formData)) {
                    return false;
                }
            }

            return true;
        }

        // 整体括号：`(amount > 1)` —— 只在成对且包住整串时剥掉
        $unwrapped = $this->unwrapParentheses($expr);

        if ($unwrapped !== $expr) {
            return $this->evaluateNormalized($unwrapped, $formData);
        }

        return $this->evaluateComparison($expr, $formData);
    }

    /**
     * @param array<string, mixed> $formData
     */
    private function evaluateComparison(string $expr, array $formData): bool
    {
        foreach (self::OPERATORS as $operator) {
            $position = strpos($expr, $operator);

            if ($position === false) {
                continue;
            }

            // `>=` 里含有 `>`，先命中的一定是最长的那条（OPERATORS 已按长度排序），
            // 但 `!=` 不会被 `=` 误伤——`=` 不在运算符表里，所以 `a = 1` 会走到下面报错
            $left = trim(substr($expr, 0, $position));
            $right = trim(substr($expr, $position + \strlen($operator)));

            if ($left === '' || $right === '') {
                throw WorkflowExecutionException::conditionInvalid($expr, '运算符两侧不能为空');
            }

            return $this->compare(
                $this->resolveOperand($left, $formData),
                $operator,
                $this->resolveOperand($right, $formData),
                $expr
            );
        }

        throw WorkflowExecutionException::conditionInvalid(
            $expr,
            '只支持 > >= < <= == != 以及 && / || 组合（复杂表达式请换 qlexpress）'
        );
    }

    /**
     * 取一个操作数的值：字符串字面量、数字、布尔、null，或表单字段。
     *
     * @param array<string, mixed> $formData
     */
    private function resolveOperand(string $token, array $formData): mixed
    {
        $token = trim($token);

        if ($token === '') {
            throw WorkflowExecutionException::conditionInvalid($token, '缺少操作数');
        }

        // 字符串字面量：支持单双引号；只还原 \\ \' \" 三种转义
        if (preg_match('/^([\'"])(.*)\1$/sD', $token, $matches) === 1) {
            return strtr($matches[2], ['\\\\' => '\\', '\\\'' => '\'', '\\"' => '"']);
        }

        if (preg_match('/^-?\d+$/D', $token) === 1) {
            return (int) $token;
        }

        if (preg_match('/^-?\d*\.\d+$/D', $token) === 1) {
            return (float) $token;
        }

        $lower = strtolower($token);

        if ($lower === 'true') {
            return true;
        }

        if ($lower === 'false') {
            return false;
        }

        if ($lower === 'null') {
            return null;
        }

        // 其余当作表单字段名。取不到给 null，由比较逻辑决定结果：
        // 缺失字段在 `!=` 下为真、在 `>` 下为假，这与"空值不满足任何大小关系"一致。
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $token) !== 1) {
            throw WorkflowExecutionException::conditionInvalid($token, '无法识别的操作数');
        }

        return $formData[$token] ?? null;
    }

    private function compare(mixed $left, string $operator, mixed $right, string $expr): bool
    {
        if ($operator === '==' || $operator === '!=') {
            $equal = $this->equals($left, $right);

            return $operator === '==' ? $equal : !$equal;
        }

        // 大小比较：一边是 null 或布尔就判假——`null > 100` 没有业务含义，
        // 让它为真会让"没填金额"的单据走到大额分支
        if ($left === null || $right === null || \is_bool($left) || \is_bool($right)) {
            return false;
        }

        $comparison = ($this->isNumeric($left) && $this->isNumeric($right))
            ? ((float) $left <=> (float) $right)
            : strcmp((string) $left, (string) $right);

        $comparison = $comparison <=> 0;

        return match ($operator) {
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            default => throw WorkflowExecutionException::conditionInvalid($expr, '未知运算符 ' . $operator),
        };
    }

    private function equals(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        if (\is_bool($left) || \is_bool($right)) {
            return $this->toBool($left) === $this->toBool($right);
        }

        // 数字与数字、数字与数字字符串都按数值比：表单里 number 类型的值
        // 可能以字符串提交（`"5000"`），按字符串比会让 `== 5000` 意外为假
        if ($this->isNumeric($left) && $this->isNumeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    private function toBool(mixed $value): bool
    {
        if (\is_string($value)) {
            return !\in_array(strtolower($value), ['', '0', 'false'], true);
        }

        return (bool) $value;
    }

    private function isNumeric(mixed $value): bool
    {
        return \is_int($value) || \is_float($value) || (\is_string($value) && is_numeric($value));
    }

    /**
     * 在顶层切分（不进入引号与括号内部）。
     *
     * @return list<string>
     */
    private function splitTopLevel(string $expr, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = \strlen($expr);
        $separatorLength = \strlen($separator);

        for ($i = 0; $i < $length; ++$i) {
            $char = $expr[$i];

            if ($quote !== null) {
                $buffer .= $char;

                if ($char === $quote && ($i === 0 || $expr[$i - 1] !== '\\')) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === '\'') {
                $quote = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === '(') {
                ++$depth;
                $buffer .= $char;

                continue;
            }

            if ($char === ')') {
                --$depth;
                $buffer .= $char;

                continue;
            }

            if ($depth === 0 && substr($expr, $i, $separatorLength) === $separator) {
                $parts[] = trim($buffer);
                $buffer = '';
                $i += $separatorLength - 1;

                continue;
            }

            $buffer .= $char;
        }

        $parts[] = trim($buffer);

        return $parts;
    }

    private function unwrapParentheses(string $expr): string
    {
        if (!str_starts_with($expr, '(') || !str_ends_with($expr, ')')) {
            return $expr;
        }

        $depth = 0;
        $length = \strlen($expr);

        for ($i = 0; $i < $length; ++$i) {
            if ($expr[$i] === '(') {
                ++$depth;
            } elseif ($expr[$i] === ')') {
                --$depth;

                // 第一个括号在中间就闭合了，说明它不是包住整串的外层括号
                if ($depth === 0 && $i !== $length - 1) {
                    return $expr;
                }
            }
        }

        return trim(substr($expr, 1, -1));
    }
}
