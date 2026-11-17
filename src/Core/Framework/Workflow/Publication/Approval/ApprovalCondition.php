<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Brick\Math\BigDecimal;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * Compile typed request comparisons and boolean groups into a self-contained snapshot.
 *
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalCondition
{
    public const PREFIX = 'shopwell:workflow:condition:v1:';
    public const MAX_DEPTH = 6;
    public const MAX_CONDITIONS = 100;

    private const GROUP_OPERATORS = ['and', 'or'];
    private const EMPTY_OPERATORS = ['isEmpty', 'isNotEmpty'];
    private const OPERATORS = ['equals', 'notEquals', 'isEmpty', 'isNotEmpty', 'greaterThan', 'greaterThanOrEqual', 'lessThan', 'lessThanOrEqual'];

    public function __construct(private WorkflowInputSchema $forms = new WorkflowInputSchema())
    {
    }

    /**
     * @param array<string, mixed> $branch
     * @param list<array<string, mixed>> $schema
     */
    public function encode(array $branch, array $schema): string
    {
        $fields = [];
        foreach ($schema as $field) {
            $fields['request.' . $field['key']] = $field;
        }

        unset($branch['id'], $branch['label']);
        $remaining = self::MAX_CONDITIONS;
        $condition = $this->normalize($branch, $fields, 0, $remaining);

        return self::PREFIX . json_encode($this->serialize($condition), \JSON_THROW_ON_ERROR);
    }

    public function decode(string $expression): ApprovalConditionNode
    {
        try {
            $raw = json_decode(substr($expression, \strlen(self::PREFIX)), true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw WorkflowExecutionException::invalidGraph('Invalid condition configuration.');
        }

        $remaining = self::MAX_CONDITIONS;

        return $this->normalize($raw, null, 0, $remaining);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function referencedFields(string $expression): array
    {
        return $this->collectFields($this->decode($expression));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function evaluate(string $expression, array $data): bool
    {
        // Validate every branch before evaluation, including branches skipped by short-circuiting.
        return $this->matches($this->decode($expression), $data);
    }

    /**
     * A null field map means the input is a published snapshot with embedded field definitions.
     *
     * @param array<string, array<string, mixed>>|null $fields
     */
    private function normalize(mixed $raw, ?array $fields, int $depth, int &$remaining): ApprovalConditionNode
    {
        if ($depth > self::MAX_DEPTH || --$remaining < 0) {
            throw WorkflowExecutionException::invalidGraph('The condition exceeds the supported size or nesting depth.');
        }

        $condition = $raw instanceof \stdClass ? get_object_vars($raw) : $raw;
        if (!\is_array($condition) || array_is_list($condition)) {
            throw WorkflowExecutionException::invalidGraph('A condition must be an object.');
        }

        $operator = $condition['operator'] ?? null;
        if (\in_array($operator, self::GROUP_OPERATORS, true)) {
            $children = $condition['conditions'] ?? null;
            if (array_diff(array_keys($condition), ['operator', 'conditions']) !== [] || !\is_array($children) || !array_is_list($children) || $children === []) {
                throw WorkflowExecutionException::invalidGraph('A condition group requires a non-empty list of conditions.');
            }

            $nodes = [];
            foreach ($children as $child) {
                $nodes[] = $this->normalize($child, $fields, $depth + 1, $remaining);
            }

            return new ApprovalConditionNode($operator, conditions: $nodes);
        }

        if (!\is_string($operator) || !\in_array($operator, self::OPERATORS, true) || !\is_string($condition['value'] ?? null)) {
            throw WorkflowExecutionException::invalidGraph('Invalid condition configuration.');
        }

        if ($fields === null) {
            if (array_diff(array_keys($condition), ['field', 'operator', 'value']) !== []) {
                throw WorkflowExecutionException::invalidGraph('Invalid comparison configuration.');
            }
            $field = $this->forms->normalize([$condition['field'] ?? null])[0];
        } else {
            if (array_diff(array_keys($condition), ['field', 'fieldType', 'operator', 'value']) !== []) {
                throw WorkflowExecutionException::invalidGraph('Invalid comparison configuration.');
            }
            $path = $condition['field'] ?? null;
            $field = \is_string($path) ? ($fields[$path] ?? null) : null;
            if ($field === null || ($condition['fieldType'] ?? null) !== $field['type']) {
                throw WorkflowExecutionException::invalidGraph('The condition field is missing or its type has changed. Select the field again.');
            }
        }

        $ordered = !\in_array($operator, ['equals', 'notEquals', ...self::EMPTY_OPERATORS], true);
        if ($ordered && !\in_array($field['type'], ['amount', 'number', 'date'], true)) {
            throw WorkflowExecutionException::invalidGraph('This field type does not support ordered comparisons.');
        }

        $value = \in_array($operator, self::EMPTY_OPERATORS, true) ? '' : $this->forms->normalizeValue($field, $condition['value']);

        return new ApprovalConditionNode($operator, field: $field, value: $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(ApprovalConditionNode $condition): array
    {
        if ($condition->field !== null) {
            return ['field' => $condition->field, 'operator' => $condition->operator, 'value' => $condition->value];
        }

        return [
            'operator' => $condition->operator,
            'conditions' => array_map($this->serialize(...), $condition->conditions),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectFields(ApprovalConditionNode $condition): array
    {
        if ($condition->field !== null) {
            return [$condition->field];
        }

        $fields = [];
        foreach ($condition->conditions as $child) {
            array_push($fields, ...$this->collectFields($child));
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function matches(ApprovalConditionNode $condition, array $data): bool
    {
        if ($condition->field === null) {
            foreach ($condition->conditions as $child) {
                $matches = $this->matches($child, $data);
                if ($condition->operator === 'and' && !$matches) {
                    return false;
                }
                if ($condition->operator === 'or' && $matches) {
                    return true;
                }
            }

            return $condition->operator === 'and';
        }

        $field = $condition->field;
        $value = $data[$field['key']] ?? null;
        $empty = $value === null || $value === '' || ($field['type'] === 'text' && \is_string($value) && trim($value) === '');
        if ($condition->operator === 'isEmpty') {
            return $empty;
        }
        if ($condition->operator === 'isNotEmpty') {
            return !$empty;
        }
        if ($empty) {
            return false;
        }

        $actual = $this->forms->normalizeValue($field, $value);
        $comparison = \in_array($field['type'], ['amount', 'number'], true)
            ? BigDecimal::of($actual)->compareTo($condition->value)
            : strcmp($actual, $condition->value);

        return match ($condition->operator) {
            'equals' => $comparison === 0,
            'notEquals' => $comparison !== 0,
            'greaterThan' => $comparison > 0,
            'greaterThanOrEqual' => $comparison >= 0,
            'lessThan' => $comparison < 0,
            'lessThanOrEqual' => $comparison <= 0,
            default => false,
        };
    }
}
