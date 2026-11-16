<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Condition;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Evaluates explicit comparisons joined by && or || over form values.
 * Parentheses group conditions; function calls and arithmetic are rejected.
 * Published approval workflows use typed ApprovalCondition expressions instead.
 *
 * @internal
 */
#[Package('framework')]
final class ComparisonConditionEvaluator implements ConditionEvaluatorInterface
{
    public function __construct(private readonly ExprNormalizer $normalizer)
    {
    }

    public function evaluate(string $expr, array $formData): bool
    {
        $stack = [];

        foreach ($this->normalizer->parse($expr) as $token) {
            if ($token['type'] === 'literal') {
                $stack[] = $token['value'];

                continue;
            }

            if ($token['type'] === 'variable') {
                $stack[] = $formData[(string) $token['value']] ?? null;

                continue;
            }

            $right = array_pop($stack);
            $left = array_pop($stack);
            $stack[] = match ($token['type']) {
                '&&' => $left && $right,
                '||' => $left || $right,
                default => $this->compare($left, $token['type'], $right, $expr),
            };
        }

        return (bool) array_pop($stack);
    }

    private function compare(mixed $left, string $operator, mixed $right, string $expr): bool
    {
        if ($operator === '==' || $operator === '!=') {
            $equal = $this->equals($left, $right);

            return $operator === '==' ? $equal : !$equal;
        }

        // Null and boolean values cannot take part in ordered comparisons.
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
            default => throw WorkflowExecutionException::conditionInvalid($expr, 'Unknown operator ' . $operator),
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

        // Compare numbers and numeric strings numerically because form values may arrive as strings.
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
}
