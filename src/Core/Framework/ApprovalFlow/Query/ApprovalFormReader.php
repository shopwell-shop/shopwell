<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Query;

use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\ApprovalFieldConfiguration;
use Shopwell\Core\Framework\ApprovalFlow\Publication\NodePlanCompiler;
use Shopwell\Core\Framework\Log\Package;

/**
 * Projects the frozen form using only rules from nodes assigned to this viewer.
 *
 * @phpstan-import-type NodePlan from NodePlanCompiler
 *
 * @phpstan-type FieldRules object{fields: list<object{field: string, access: string}>}&\stdClass
 *
 * @internal
 */
#[Package('framework')]
class ApprovalFormReader
{
    /**
     * @param list<string> $assignedNodeIds
     */
    public function read(string $formJson, string $nodePlanJson, array $assignedNodeIds): \stdClass
    {
        /** @var NodePlan $plan */
        $plan = json_decode($nodePlanJson, flags: \JSON_THROW_ON_ERROR);
        $rules = [];
        foreach ($plan->nodes as $node) {
            if (!\in_array($node->nodeId, $assignedNodeIds, true) || $node->type !== CoreNodeType::APPROVAL || $node->typeVersion !== '1.0.0') {
                continue;
            }
            // These are immutable, validated publication bytes, not a new plugin configuration input.
            /** @var FieldRules $configuration */
            $configuration = $node->configuration;
            foreach ($configuration->fields as $field) {
                $rules[$field->field] = ($rules[$field->field] ?? true) && $field->access !== ApprovalFieldConfiguration::HIDDEN;
            }
        }
        $form = json_decode($formJson, flags: \JSON_THROW_ON_ERROR);
        \assert($form instanceof \stdClass);

        return $this->project($form, $rules, 'form', inheritedAccess: false);
    }

    public function labels(\stdClass $visibleForm, string $schemaJson): \stdClass
    {
        $schema = json_decode($schemaJson, true, flags: \JSON_THROW_ON_ERROR);
        $labels = new \stdClass();
        $this->collectLabels($visibleForm, $schema, 'form', $labels);

        return $labels;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function collectLabels(\stdClass $form, array $schema, string $prefix, \stdClass $labels): void
    {
        foreach (get_object_vars($form) as $name => $value) {
            $key = (string) $name;
            $path = $prefix . '.' . $key;
            $property = $schema['properties'][$key] ?? [];
            $property = \is_array($property) ? $property : [];
            $labels->$path = [
                'title' => \is_string($property['title'] ?? null) ? $property['title'] : $key,
                'labelSnippet' => \is_string($property['x-shopwell-label-snippet'] ?? null) ? $property['x-shopwell-label-snippet'] : null,
            ];
            if ($value instanceof \stdClass) {
                $this->collectLabels($value, $property, $path, $labels);
            }
        }
    }

    /**
     * @param array<string, bool> $rules
     */
    private function project(\stdClass $form, array $rules, string $prefix, bool $inheritedAccess): \stdClass
    {
        $visible = new \stdClass();
        foreach (get_object_vars($form) as $name => $value) {
            // PHP converts numeric property names to integer keys when materializing this array.
            $key = (string) $name;
            // The current field reference syntax has no escape for a dot inside a property name.
            if (str_contains($key, '.')) {
                continue;
            }
            $path = $prefix . '.' . $key;
            if (($rules[$path] ?? null) === false) {
                continue;
            }
            $allowed = $rules[$path] ?? $inheritedAccess;
            if ($value instanceof \stdClass) {
                $children = $this->project($value, $rules, $path, $allowed);
                if ($allowed || get_object_vars($children) !== []) {
                    $visible->$key = $children;
                }
                continue;
            }
            if (!$allowed) {
                continue;
            }
            if (\is_array($value)) {
                foreach ($rules as $field => $access) {
                    // Lists are atomic until the form contract defines element-level projection.
                    if (str_starts_with($field, $path . '.')) {
                        continue 2;
                    }
                }
            }
            $visible->$key = $value;
        }

        return $visible;
    }
}
