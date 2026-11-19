<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
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
     * @param non-empty-list<string> $userIds
     * @param self::SINGLE|self::ALL|self::ANY $policy
     * @param list<ApprovalFieldConfiguration> $fields
     * @param self::EMPTY_INCIDENT|self::EMPTY_SKIP $emptyAssignees
     */
    public function __construct(
        public array $userIds,
        public string $policy,
        public array $fields,
        public string $emptyAssignees,
    ) {
    }

    /**
     * @return array{
     *     userIds: non-empty-list<string>,
     *     policy: self::SINGLE|self::ALL|self::ANY,
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
            'reject' => self::REJECT_VETO,
            'fields' => array_map(static fn (ApprovalFieldConfiguration $field): array => $field->jsonSerialize(), $this->fields),
            'emptyAssignees' => $this->emptyAssignees,
        ];
    }
}
