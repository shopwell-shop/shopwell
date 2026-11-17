<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Form;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;

/**
 * @internal
 */
#[Package('framework')]
final class ApprovalFormPolicy
{
    public const HIDDEN = 'hidden';
    public const READONLY = 'readonly';
    public const EDITABLE = 'editable';

    /**
     * Missing entries are read-only. Node requirements apply only to editable fields.
     *
     * @param list<array<string, mixed>> $schema
     *
     * @return array<string, array{access: string, required: bool}>
     */
    public function normalize(mixed $configuration, array $schema, bool $allowEdit = true): array
    {
        if ($configuration instanceof \stdClass) {
            $configuration = get_object_vars($configuration);
        }
        if (!\is_array($configuration) || ($configuration !== [] && array_is_list($configuration))) {
            throw WorkflowExecutionException::invalidGraph('Form permissions must be an object keyed by field.');
        }
        if (array_diff(array_keys($configuration), array_column($schema, 'key')) !== []) {
            throw WorkflowExecutionException::invalidGraph('Form permissions reference an unknown field.');
        }

        $permissions = [];
        foreach ($schema as $field) {
            $key = $field['key'];
            $permission = \array_key_exists($key, $configuration) ? $configuration[$key] : ['access' => self::READONLY];
            if ($permission instanceof \stdClass) {
                $permission = get_object_vars($permission);
            }
            if (!\is_array($permission) || array_diff(array_keys($permission), ['access', 'required']) !== []) {
                throw WorkflowExecutionException::invalidGraph('Invalid form permission configuration for: ' . $key);
            }
            $access = $permission['access'] ?? null;
            $required = $permission['required'] ?? false;
            if (!\in_array($access, [self::HIDDEN, self::READONLY, self::EDITABLE], true) || !\is_bool($required)) {
                throw WorkflowExecutionException::invalidGraph('Invalid form access or requirement for: ' . $key);
            }
            if ((!$allowEdit && $access === self::EDITABLE) || ($required && $access !== self::EDITABLE)) {
                throw WorkflowExecutionException::invalidGraph('Only editable approval fields can be required at a node: ' . $key);
            }

            $permissions[$key] = ['access' => $access, 'required' => $required];
        }

        return $permissions;
    }
}
