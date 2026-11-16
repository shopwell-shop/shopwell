<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication\Approval;

use Brick\Math\BigDecimal;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Definition\Input\WorkflowInputSchema;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalCondition
{
    public const PREFIX = 'shopwell:workflow:condition:v1:';

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
        $field = null;
        foreach ($schema as $candidate) {
            if ('request.' . $candidate['key'] === ($branch['field'] ?? null)) {
                $field = $candidate;
                break;
            }
        }
        if ($field === null || ($branch['fieldType'] ?? null) !== $field['type']) {
            throw WorkflowExecutionException::invalidGraph('The condition field is missing or its type has changed. Select the field again.');
        }
        $condition = ['field' => $field, 'operator' => $branch['operator'] ?? null, 'value' => $branch['value'] ?? null];
        $encoded = self::PREFIX . json_encode($condition, \JSON_THROW_ON_ERROR);
        $this->decode($encoded);

        return $encoded;
    }

    /**
     * @return array{field: array<string, mixed>, operator: string, value: string}
     */
    public function decode(string $expression): array
    {
        try {
            $condition = json_decode(substr($expression, \strlen(self::PREFIX)), true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw WorkflowExecutionException::invalidGraph('Invalid condition configuration.');
        }
        if (!\is_array($condition) || !\in_array($condition['operator'] ?? null, self::OPERATORS, true) || !\is_string($condition['value'] ?? null)) {
            throw WorkflowExecutionException::invalidGraph('Invalid condition configuration.');
        }
        $field = $this->forms->normalize([$condition['field'] ?? null])[0];
        $operator = $condition['operator'];
        if (!\in_array($operator, ['equals', 'notEquals', 'isEmpty', 'isNotEmpty'], true) && !\in_array($field['type'], ['amount', 'number', 'date'], true)) {
            throw WorkflowExecutionException::invalidGraph('This field type does not support ordered comparisons.');
        }
        $value = \in_array($operator, ['isEmpty', 'isNotEmpty'], true) ? '' : $this->forms->normalizeValue($field, $condition['value']);

        return ['field' => $field, 'operator' => $operator, 'value' => $value];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function evaluate(string $expression, array $data): bool
    {
        $condition = $this->decode($expression);
        $field = $condition['field'];
        $value = $data[$field['key']] ?? null;
        $empty = $value === null || $value === '' || ($field['type'] === 'text' && \is_string($value) && trim($value) === '');
        if ($condition['operator'] === 'isEmpty') {
            return $empty;
        }
        if ($condition['operator'] === 'isNotEmpty') {
            return !$empty;
        }
        if ($empty) {
            return false;
        }
        $actual = $this->forms->normalizeValue($field, $value);
        $compare = \in_array($field['type'], ['amount', 'number'], true)
            ? BigDecimal::of($actual)->compareTo($condition['value']) : strcmp($actual, $condition['value']);

        return match ($condition['operator']) {
            'equals' => $compare === 0,
            'notEquals' => $compare !== 0,
            'greaterThan' => $compare > 0,
            'greaterThanOrEqual' => $compare >= 0,
            'lessThan' => $compare < 0,
            'lessThanOrEqual' => $compare <= 0,
            default => false,
        };
    }
}
