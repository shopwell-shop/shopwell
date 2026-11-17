<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Form;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Business subscribers may restrict published permissions, never grant additional access.
 * Restrictions accumulate independently of listener order.
 */
#[Package('framework')]
final class ApprovalFormPolicyEvent extends Event
{
    /**
     * @var array<string, string>
     */
    private array $restrictions = [];

    public function __construct(
        public readonly WorkflowInstanceEntity $instance,
        public readonly ?string $nodeId,
        public readonly string $actorId,
        public readonly Context $context,
    ) {
    }

    public function hide(string $key): void
    {
        $this->restrictions[$key] = ApprovalFormPolicy::HIDDEN;
    }

    public function readOnly(string $key): void
    {
        $this->restrictions[$key] ??= ApprovalFormPolicy::READONLY;
    }

    /**
     * @internal
     *
     * @param array<string, array{access: string, required: bool}> $permissions
     *
     * @return array<string, array{access: string, required: bool}>
     */
    public function restrict(array $permissions): array
    {
        foreach ($permissions as $key => &$permission) {
            $restriction = $this->restrictions[$key] ?? null;
            if ($restriction === ApprovalFormPolicy::HIDDEN || ($restriction === ApprovalFormPolicy::READONLY && $permission['access'] === ApprovalFormPolicy::EDITABLE)) {
                $permission = ['access' => $restriction, 'required' => false];
            }
        }
        unset($permission);

        return $permissions;
    }
}
