<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * Compiled approval rules shared by compilers, executors and task-planning intents.
 */
#[Package('framework')]
final readonly class ApprovalNodeConfiguration implements NodeConfiguration
{
    public const SINGLE = 'core:single';

    public const ALL = 'core:all';

    public const ANY = 'core:any';

    public const REJECT_VETO = 'veto';

    public const EMPTY_INCIDENT = 'incident';

    public const EMPTY_SKIP = 'skip';

    /**
     * @var non-empty-list<string>
     */
    public array $userIds;

    /**
     * @var list<ApprovalFieldConfiguration>
     */
    public array $fields;

    /**
     * @param list<string> $userIds
     * @param list<ApprovalFieldConfiguration> $fields
     * @param self::EMPTY_INCIDENT|self::EMPTY_SKIP $emptyAssignees
     */
    public function __construct(
        array $userIds,
        public string $policy,
        array $fields,
        public string $emptyAssignees,
        public string $policyVersion = '1.0.0',
    ) {
        if ($userIds === []) {
            throw ApprovalFlowException::emptyApprovalAssignees();
        }
        if ($policy === self::SINGLE && \count($userIds) !== 1) {
            throw ApprovalFlowException::singleApprovalAssigneeRequired();
        }

        // Copy list entries by value so callers cannot change the rules through array references.
        $this->userIds = array_map(static fn (string $userId): string => $userId, $userIds);
        $this->fields = array_map(static fn (ApprovalFieldConfiguration $field): ApprovalFieldConfiguration => $field, $fields);
    }

    /**
     * @return array{
     *     userIds: non-empty-list<string>,
     *     policy: string,
     *     policyVersion: string,
     *     reject: 'veto',
     *     fields: list<array{field: string, access: 'hidden'|'readOnly'|'editable', required: bool}>,
     *     emptyAssignees: self::EMPTY_INCIDENT|self::EMPTY_SKIP
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'userIds' => $this->userIds,
            'policy' => $this->policy,
            'policyVersion' => $this->policyVersion,
            'reject' => self::REJECT_VETO,
            'fields' => array_map(static fn (ApprovalFieldConfiguration $field): array => $field->jsonSerialize(), $this->fields),
            'emptyAssignees' => $this->emptyAssignees,
        ];
    }
}
