<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution\Assignee;

use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowAssigneeTypes;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionContext;
use Shopwell\Core\Framework\Workflow\Instance\WorkflowInstanceEntity;
use Shopwell\Core\System\User\UserCollection;

/**
 * Resolve active users through the platform ACL role association, ordered by user ID.
 *
 * @internal
 */
#[Package('framework')]
final class RoleAssigneeResolver implements AssigneeResolverInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(private readonly EntityRepository $userRepository)
    {
    }

    public function getAssigneeType(): string
    {
        return WorkflowAssigneeTypes::ROLE;
    }

    public function resolve(WorkflowInstanceEntity $instance, GraphNode $node, WorkflowExecutionContext $context): array
    {
        $value = $node->property('assigneeValue');

        if (!\is_array($value)) {
            return [];
        }

        $roleIds = array_values(array_filter($value, static fn (mixed $id): bool => \is_string($id) && $id !== ''));

        if ($roleIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsAnyFilter('aclRoles.id', $roleIds));

        $criteria->addAssociation('aclRoles');
        $users = $this->userRepository->search($criteria, $context->context())->getEntities();
        $userIds = $users->filter(ApprovalUserEligibility::isEligible(...))->getIds();
        sort($userIds);

        return $userIds;
    }
}
